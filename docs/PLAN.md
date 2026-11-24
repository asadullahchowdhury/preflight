# Preflight: build plan (approved 2026-11-24)

## Context

Preflight reviews Laravel database migrations in pull requests. It says how each statement will behave on the **real production table**: the MySQL online-DDL algorithm (INSTANT / INPLACE / COPY), whether writes are blocked, a duration range, and a safer rewrite. The user picked it from a product-discovery session.

The MVP is **Laravel + MySQL 8 only**. It reports through a GitHub check and a PR comment, and the verdict uses **no AI**: it is a deterministic rules engine.

Decisions the user has made:

| Decision | Choice |
|---|---|
| Project location | `D:\laragon\www\preflight` (new git repo; the portfolio repo stays untouched) |
| How we get the SQL | In the **customer's CI**. A GitHub Action runs our open-source `php artisan preflight:check`, which runs `migrate --pretend` and sends only the SQL. We never execute customer code. |
| How we get table sizes | **Pushed from their app**. `php artisan preflight:stats`, scheduled in production, sends row counts and data/index bytes outbound. Their DB is never exposed, and the package doubles as the "agent". |
| Dashboard frontend | Laravel + **Inertia + React** (TypeScript, shadcn/ui starter kit) |

Local environment checked: PHP 8.4, Composer 2.10, Node 24, MySQL 8.0.30 (Laragon), git, and gh are installed. Docker is installed but its daemon is not running; it's only needed from M2.

## Architecture

```
Customer CI (GitHub Actions)                         Preflight Cloud (Laravel + Inertia React)
┌────────────────────────────────────────┐           ┌───────────────────────────────────────┐
│ mysql:<prod version> service container │           │ POST /api/analyses  (GitHub OIDC JWT)  │
│ php artisan preflight:check            │──SQL+DDL─▶│ joins latest table_stats               │
│  1. migrate base-branch migrations     │  (no data)│ runs core Analyzer in size-aware mode  │
│  2. SHOW CREATE TABLE (before-state)   │           │ GitHub App → check run + PR comment    │
│  3. migrate --pretend new migrations   │           │ dashboard: repos, stats, analyses      │
│  4. core Analyzer (static verdict)     │           └───────────────▲───────────────────────┘
└────────────────────────────────────────┘                           │ table sizes only
                                                   Customer prod app: │ php artisan preflight:stats
                                                   (scheduler, token) ┘ (rows, data/index bytes, version)
```

- **Free tier (open source, works with no account):** the Action posts a static verdict as a PR comment or job summary using `GITHUB_TOKEN`. Example: "COPY, so writes are blocked for the whole rebuild."
- **Paid tier:** the same SQL goes to the cloud. The cloud adds production sizes, duration ranges, history, the GitHub App identity, and the dashboard.
- **Auth from CI uses GitHub's OIDC token,** so customers store no secrets. The cloud verifies the JWT against GitHub's JWKS and maps the `repository_id` claim to an installed repo.

## Repo split

One **monorepo** at `D:\laragon\www\preflight`, kept private. The MIT packages get published later as read-only public mirrors via `git subtree split`, the same pattern Laravel uses for its framework packages. One repo is easier for a solo developer, and the split happens only when publishing.

```
preflight/
  packages/
    core/        # MIT. Framework-agnostic PHP: SQL parsing, schema snapshot, rules, estimator   ← M1
    laravel/     # MIT. Laravel package: preflight:check, preflight:stats                          ← M2
  action/        # MIT. GitHub composite action (uses: <org>/preflight-action@v1)                 ← M3
  apps/cloud/    # Private. Laravel + Inertia React SaaS                                           ← M4+
  docs/
```

## Core engine design (`packages/core`)

```
src/
  Analyzer.php                  analyze(string[] $sql, SchemaSnapshot $before, ServerProfile $server, ?StatsSet $stats): Report
  Server/ServerProfile.php      version (8.0.x / 8.4.x), relevant vars (foreign_key_checks, sql_mode)
  Schema/                       SchemaSnapshot, Table, Column, Index, ForeignKey (built from CREATE TABLE text)
  Sql/StatementParser.php       wraps phpmyadmin/sql-parser → list of typed Operation objects
  Sql/ColumnDiff.php            old vs new column definition → DefaultOnly | VarcharExtend | Nullability | TypeChange | …
  Operations/                   AddColumn, DropColumn, RenameColumn, ModifyColumn, AddIndex, DropIndex,
                                AddFulltextIndex, AddPrimaryKey, AddForeignKey, DropForeignKey,
                                ChangeEngine, ConvertCharset, RenameTable, CreateTable, DropTable, Unknown
  Rules/MySql/                  one rule class per operation family, version-aware
  Estimate/DurationEstimator.php  bytes + operation → [minSeconds, maxSeconds] (throughput band, later calibrated)
  Report/                       Report, Finding, Severity (info|warn|danger), Algorithm, LockLevel
```

**`Finding` fields:** `ruleId`, `table`, `statement`, `operation`, `algorithm`, `lock`, `rebuildsTable`, `blocksWrites`, `severity`, `message`, `suggestion`, and `estimate` (present only when stats are given).

**Initial rule catalog.** These rules encode MySQL's online-DDL documentation, and the oracle tests below check them against real servers:

- **Add column:**
  - INSTANT on 8.0.12+ when added last.
  - INSTANT at any position on 8.0.29+.
  - Otherwise an INPLACE rebuild.
  - Falls back to a rebuild for `ROW_FORMAT=COMPRESSED` tables, tables with a FULLTEXT index, or tables that hit the instant row-version limit.
- **Drop column:** INSTANT on 8.0.29+; otherwise an INPLACE rebuild.
- **Rename column:** INSTANT on 8.0.28+; INPLACE metadata-only before that.
- **Set or drop a default:** INSTANT.
- **`MODIFY` (Laravel `->change()`), classified by diffing against the before-schema:**
  - Default change only: instant.
  - `VARCHAR` extended within the same length-byte count (≤255 bytes): INPLACE, metadata only.
  - Crossing the 255-byte boundary, or any type change: **COPY, blocks writes**.
  - NULL/NOT NULL change: INPLACE rebuild.
- **Add index / unique:** INPLACE, no rebuild, writes allowed (cost grows with table size).
- **Drop or rename an index:** metadata only.
- **Add FULLTEXT or SPATIAL index:** INPLACE with `LOCK=SHARED`, **blocks writes**.
- **Add primary key:** INPLACE rebuild. Dropping a PK without adding one: COPY.
- **Add foreign key:** **COPY unless `foreign_key_checks=0`**. This is a key catch for Laravel's `foreignId()->constrained()` on existing tables.
- **Change engine or convert charset:** COPY. Adding a STORED generated column: COPY. Adding a VIRTUAL one: instant.
- **Cross-cutting rules:**
  - `mdl-wait`: every ALTER, including INSTANT ones, needs an exclusive metadata lock and will queue behind long transactions. Recommend a short `lock_wait_timeout`.
  - `multiple-rebuilds`: several rebuilding ALTERs on the same table in one migration. Suggest combining them.
  - `drop-table-data-loss`: warn when a table is dropped.
  - `unknown-statement`: anything the parser can't classify gets a `warn` finding, never a silent pass.

**Static severity:** blocks writes (COPY or `LOCK=SHARED`) → `danger`. Rebuild or index build → `warn`. INSTANT or metadata-only → `info`. When stats are present (M5), severity is set by the estimated duration against configurable thresholds.

**How rules are tested:**

1. **Fixture tests (the source of truth).** Each YAML file in `tests/Fixtures/mysql/*.yaml` holds `server.version`, the `schema` (CREATE TABLE text), the `sql`, and the expected list of `{rule, algorithm, lock, rebuild, severity}`. A single Pest dataset test runs every fixture. Example:
   ```yaml
   name: add column after another column rebuilds before 8.0.29
   server: { version: "8.0.28" }
   schema: "CREATE TABLE orders (id bigint unsigned NOT NULL AUTO_INCREMENT, status varchar(20) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB"
   sql: "alter table `orders` add `note` varchar(255) null after `id`"
   expect: [{ rule: add-column, algorithm: INPLACE, lock: NONE, rebuild: true, severity: warn }]
   ```
2. **Oracle tests (ground truth from real MySQL), `@group oracle`.** For each fixture: create the schema in a scratch DB, then run the SQL with `, ALGORITHM=<expected>, LOCK=<expected>` appended; it must succeed. Then run it with the next stricter algorithm (e.g. INSTANT when INPLACE is expected); MySQL must reject it with error 1845/1846. This proves the rules match MySQL's own behaviour. In M1 this runs against the local Laragon MySQL 8.0.30 through `PREFLIGHT_ORACLE_DSN`, using a scratch database `preflight_oracle` only. From M2, a Docker matrix covers several 8.0.x versions and 8.4 in CI.
3. **Unit tests** for `ColumnDiff`, `StatementParser`, and `DurationEstimator`.

**Tooling:** Pest, PHPStan (max level), and Pint. Core needs PHP ≥ 8.2 so it installs on older customer stacks; development happens on 8.4. Runtime dependencies are `phpmyadmin/sql-parser` only, plus `symfony/yaml` as a dev dependency.

## Cloud data model (`apps/cloud`, M4+)

- `users`: GitHub OAuth login.
- `accounts`: GitHub org or user (github_id, login, type, plan).
- `installations`: github_installation_id, account_id.
- `repositories`: github_repo_id, full_name, installation_id, settings JSON (MySQL version, thresholds, comment mode).
- `stats_sources`: repository_id, token_hash, server_version, server_vars JSON, last_pushed_at.
- `table_stats`: stats_source_id, table_name, rows_estimate, data_bytes, index_bytes, engine, row_format, has_fulltext, captured_at. The latest snapshot is kept, plus daily history.
- `analyses`: repository_id, pr_number, base_sha, head_sha, status, overall_severity, check_run_id, comment_id, payload JSON.
- `findings`: analysis_id plus the `Finding` fields and est_seconds_min/max.
- `calibration_samples` (later): observed migration durations used to refine throughput.

**GitHub App:** permissions are `checks:write`, `pull_requests:write`, and `metadata:read`. Webhooks: `installation` and `installation_repositories`. It needs no `contents` access, because the SQL arrives from CI.

## Milestones

| Week | Milestone | Done when |
|---|---|---|
| **1** | **M1: core rules engine** (implemented right after approval) | Monorepo scaffolded. `packages/core` with parser, schema snapshot, ColumnDiff, the rules above, and DurationEstimator. 40+ YAML fixtures green. Oracle group green against local MySQL 8.0.30. PHPStan and Pint clean. `bin/preflight-analyze schema.sql migration.sql` prints findings. |
| 2 | M2: Laravel package | `preflight:check` migrates the base, snapshots tables, captures pretend SQL for pending migrations, and outputs a table, markdown, or JSON. Tested against a fixture Laravel app. Docker oracle matrix for 8.0.x and 8.4. **Done:** the package is tested on Laravel 11 and 13 and in a real Laravel 13 app. The oracle matrix passes on MySQL 8.0.12, 8.0.27, 8.0.28, 8.0.30, 8.0.46 and 8.4.11 (run locally through Laragon with `scripts/oracle-matrix.ps1`). It found two rules that were wrong for 8.0.12, both now fixed. |
| 3 | M3: GitHub Action (free tier) | A composite action with a MySQL service. It posts a static verdict as a PR comment (falling back to the job summary on fork PRs) and fails the job on `danger` if configured. A workflow template is in the docs. **Done:** `action/`. Its scripts were tested locally against a fresh Laravel app; the self-test workflow (`.github/workflows/action.yml`) has not run on GitHub yet. |
| 4 | M4: Cloud skeleton | Laravel + Inertia React app with GitHub login, GitHub App install flow, and repos list. The OIDC-verified `POST /api/analyses` endpoint, with the check run and PR comment posted as the App. **Done:** `apps/cloud`, with the dashboard redesigned (glass surfaces, blur-in motion, responsive) and a local demo login. Not deployed and no GitHub App registered yet, so it is tested with faked GitHub and OIDC. |
| 5 | M5: Size-aware tier | `preflight:stats --push` with a per-repo token. Stats ingestion. Size-aware severity and duration ranges in comments. Dashboard pages for stats and analysis history. |
| 6 | M6: Launch prep | Safe-rewrite suggestions polished, billing, landing page, docs site, then a private beta with 5–10 teams. |
| 7–8 | Beta | Calibration from real migrations, rule fixes driven by the oracle tests, and a public OSS release (subtree split, Packagist). |

## M1 implementation steps

1. Create `D:\laragon\www\preflight` and run `git init`. Add a root README and `.gitignore`. Commits carry **no Co-Authored-By or Claude attribution**.
2. Set up `packages/core`: `composer.json` (PSR-4 `Preflight\Core\`), Pest, PHPStan, Pint, `phpmyadmin/sql-parser`, `symfony/yaml` (dev).
3. Write the value objects (Report, Finding, Severity, Algorithm, LockLevel, ServerProfile) and the Schema snapshot built from CREATE TABLE text.
4. Write StatementParser and Operations, mapping Laravel-style SQL (backticked, lower-case keywords) into typed operations.
5. Write ColumnDiff, then the rules version by version, each driven by fixtures written first.
6. Write DurationEstimator with a default throughput band and unit tests.
7. Add the oracle test group (opt-in through an environment DSN, scratch DB only) and run it against local MySQL 8.0.30.
8. Add the `bin/preflight-analyze` dev CLI, and a README with usage and fixture authoring notes.

## Verification (M1)

- `cd packages/core && composer install && vendor/bin/pest`: all fixture and unit tests pass.
- `PREFLIGHT_ORACLE_DSN="mysql:host=127.0.0.1;dbname=preflight_oracle" vendor/bin/pest --group=oracle` against Laragon MySQL 8.0.30: every expected algorithm/lock is accepted by MySQL, and the stricter one is rejected.
- `vendor/bin/phpstan analyse` and `vendor/bin/pint --test` are clean.
- Manual check: `bin/preflight-analyze` on the example from discovery (`MODIFY status`, then `ADD INDEX` on `orders`) reports `danger`/COPY for the MODIFY and `warn`/INPLACE for the index.

## Open decisions (not blocking M1)

- **Names:** the product and Packagist vendor name. `preflight/*` is probably taken, so check availability before M2 publishing.
- **License** for the OSS parts: MIT is recommended.
- **Supported versions:** MySQL 8.0.x + 8.4 LTS for MVP. 5.7 is end-of-life; MariaDB and Postgres come later.
- **Free tier behaviour:** should the Action post a PR comment or only a job summary, and fail CI on `danger` by default or warn only?
- **Hosting:** Laravel Cloud vs Forge + VPS.
- **Billing:** Stripe isn't available in every country, so a merchant of record like Paddle may be simpler for global tax.
- **Default thresholds** (e.g. danger when blocked writes are estimated above 10 seconds). These will be tuned in beta.

## Risks

- **`phpmyadmin/sql-parser` may not cover every ALTER form.** Mitigation: an `unknown-statement` finding, never a silent pass, plus fixtures for every Laravel grammar output.
- **`TABLE_ROWS` is an estimate**, so durations are always shown as ranges.
- **Rule correctness differs across minor versions.** The oracle matrix is the safeguard and must run in CI from M2.
