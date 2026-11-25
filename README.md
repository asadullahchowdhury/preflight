# Preflight

Preflight reviews database migrations in pull requests. It tells you how each statement will behave on the **real production table**: whether MySQL runs it instantly, in place, or by copying the table and blocking writes, roughly how long it takes, and how to make it safe.

MVP scope: **Laravel + MySQL 8**, delivered as a GitHub check and PR comment. The verdict uses no AI: it's a deterministic rules engine checked against real MySQL servers.

## Repository layout

| Path | What | License | Status |
|---|---|---|---|
| [`packages/core`](packages/core) | Framework-agnostic PHP engine: SQL parsing, schema snapshot, MySQL online-DDL rules, duration estimates | MIT (planned) | **M1: done** |
| [`packages/laravel`](packages/laravel) | Laravel package: `php artisan preflight:check`, `preflight:stats` | MIT (planned) | **M2: done** |
| [`action/`](action) | GitHub composite action (free tier): PR comment, annotations, job summary, fail-on | MIT (planned) | **M3: done** (not yet run on GitHub) |
| [`apps/cloud`](apps/cloud) | Laravel + Inertia React SaaS: GitHub sign-in, GitHub App, OIDC-verified API, check runs and PR comments as the App, production table sizes, private-beta waitlist, docs, dashboard | private | **M4–M6: done** (not deployed; billing deferred) |

This is one private monorepo for now. The open-source packages get published later as read-only mirrors via `git subtree split`.

The plan, architecture, data model and milestones are in [docs/PLAN.md](docs/PLAN.md). To put Preflight Cloud online for free (Render + Aiven), follow [docs/DEPLOY.md](docs/DEPLOY.md).

## How it works

1. The customer's CI (GitHub Action) runs `php artisan preflight:check`. It migrates the base branch into a throwaway MySQL of the production version, snapshots `SHOW CREATE TABLE`, and captures `migrate --pretend` SQL for the new migrations. **Preflight never runs customer code on its own servers.**
2. `packages/core` analyses that SQL against the snapshot, giving a static verdict for free.
3. Paid tier: the SQL goes to Preflight Cloud (authenticated with GitHub OIDC, so no secrets are stored). The cloud joins it with production table sizes, which `php artisan preflight:stats` pushes from the customer's app on a schedule. Only numbers are sent, never row data. The result is posted back as a check run and PR comment.

## Development

```bash
cd packages/core && composer install && vendor/bin/pest
cd packages/laravel && composer install && vendor/bin/pest   # needs a local MySQL (see its README)

# The oracle suite against several MySQL versions: Docker, or on Windows every MySQL build in Laragon
scripts/oracle-matrix.sh            # Docker
powershell -File scripts/oracle-matrix.ps1  # Laragon: each D:/laragon/bin/mysql/mysql-8.* build on port 3307
```

- [packages/core/README.md](packages/core/README.md) covers the CLI, the fixture format and the MySQL oracle tests.
- [packages/laravel/README.md](packages/laravel/README.md) covers the artisan commands and CI usage.

CI (`.github/workflows`) runs the core tests on PHP 8.2 and 8.4. It also runs the oracle suite on MySQL 8.0.12, 8.0.27, 8.0.28, 8.0.30, 8.0 (latest) and 8.4, and the Laravel package on Laravel 11, 12 and 13.
