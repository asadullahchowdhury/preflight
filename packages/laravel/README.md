# preflight/laravel

Checks a Laravel app's new migrations for MySQL schema changes that lock or copy tables, **before** they reach production.

```
$ php artisan preflight:check --base=origin/main

Preflight · MySQL 8.0.36 · static (no table sizes)

2026_11_24_000001_add_profile_to_users
OK     alter table `users` add `timezone` varchar(64) not null default 'UTC' after `email`
        → ALGORITHM=INSTANT LOCK=NONE
DANGER alter table `users` add fulltext `users_bio_fulltext`(`bio`)
        → ALGORITHM=INPLACE LOCK=SHARED · rebuilds table · BLOCKS WRITES
          ↳ Run it in a maintenance window. Or run it with gh-ost or pt-online-schema-change, …

2026_11_24_000002_create_teams_table
DANGER alter table `users` add `team_id` bigint unsigned null
        → ALGORITHM=COPY LOCK=SHARED · rebuilds table · BLOCKS WRITES
        [add-column] … `users` has a FULLTEXT index, so MySQL can't add columns instantly or in place …
```

The analysis is done by [`preflight/core`](../core), a deterministic rules engine checked against real MySQL servers. It uses no AI, and it never sends your code or data anywhere.

Requirements: PHP 8.2+, Laravel 11, 12 or 13, and MySQL 8.0 or 8.4 (MariaDB is not supported).

## Install

```bash
composer require --dev preflight/laravel
```

## `preflight:check`

```bash
# In CI, against an empty throwaway database: build the base branch's schema, then check what the branch adds.
php artisan preflight:check --base=origin/main --markdown=preflight.md

# Locally: check pending migrations against your current database. Read-only.
php artisan preflight:check

# Check specific migrations only. Read-only.
php artisan preflight:check --migration=2026_11_24_000001_add_profile_to_users
```

How it works:

1. **Which migrations are new.**
   - With `--base`, these are the migration files the branch adds compared with the merge base. Committed, uncommitted and untracked files all count.
   - Without it, they are all pending migrations.
   - With `--migration`, they are the named ones.
2. **The "before" schema.**
   - With `--base`, pending migrations that already exist on the base branch are run first. On a fresh CI database, that rebuilds production's schema.
   - Then it reads `SHOW CREATE TABLE` for every table.
3. **The SQL.** Each new migration's `up()` runs in Laravel's pretend mode (what `migrate --pretend` does), so **nothing is executed**.
4. **The verdict.** For every statement, Preflight reports the algorithm MySQL will use (INSTANT, INPLACE or COPY), whether writes are blocked, whether the table is rebuilt, and a safer alternative. It tracks the schema across migrations: an index added by one migration changes the verdict for the next.

| Option | |
|---|---|
| `--base=REF` | Git ref of the base branch. **Runs the base branch's pending migrations** (refused in production unless `--force`). In CI, check out with `fetch-depth: 0`. |
| `--migration=NAME` | Check only these migrations (repeatable). Nothing is run. |
| `--mysql=8.0.36` | The MySQL version **production** runs. Online-DDL behaviour changes between minor versions, e.g. INSTANT ADD/DROP COLUMN arrived in 8.0.29. |
| `--stats=FILE` | Production table sizes from `preflight:stats`. Adds duration estimates and size-aware severity. |
| `--format=text\|markdown\|json` | Output format (stdout). |
| `--markdown=FILE`, `--json=FILE` | Also write the report to a file, e.g. for a PR comment. |
| `--fail-on=danger\|warn\|never` | Exit with code `1` at this severity. The default is `danger`. |
| `--database`, `--path`, `--realpath` | Same as `php artisan migrate`. |

Exit codes: `0` passes, `1` means the `--fail-on` threshold was reached, `2` means usage or setup errors.

The production version comes from `--mysql`, then `config('preflight.mysql_version')`, then the stats file, then the connected server.

## `preflight:stats`

```bash
php artisan preflight:stats --output=preflight-stats.json
```

This reads row estimates and data/index sizes from `information_schema.TABLES`: numbers only, never row data. Run it against production (for example from the scheduler), then pass the file to `preflight:check --stats=…`:

```json
{
    "preflight_stats": 1,
    "server_version": "8.0.36",
    "captured_at": "2026-11-24T09:41:56+00:00",
    "tables": {
        "orders": { "rows": 41200000, "data_bytes": 19756849152, "index_bytes": 6549825536 }
    }
}
```

With sizes, a COPY on a 2,000-row table is `info` (it blocks writes for under a second). The same change on 41M rows is `danger`, estimated at 8–42 minutes.

## Configuration

```bash
php artisan vendor:publish --tag=preflight-config
```

| Key | Env | Default |
|---|---|---|
| `mysql_version` | `PREFLIGHT_MYSQL_VERSION` | the connected server's version |
| `connection` | `PREFLIGHT_CONNECTION` | the default connection |
| `stats_path` | `PREFLIGHT_STATS_PATH` | none |
| `fail_on` | `PREFLIGHT_FAIL_ON` | `danger` |

## GitHub Actions

A packaged action that comments on the PR is coming (milestone M3). Until then, this works:

```yaml
jobs:
  preflight:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0.36            # match production
        env: { MYSQL_ALLOW_EMPTY_PASSWORD: 'yes', MYSQL_DATABASE: app }
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping -h 127.0.0.1" --health-interval=5s --health-retries=30
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - run: composer install --no-interaction --no-progress
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan preflight:check --base=origin/${{ github.base_ref }} --markdown=preflight.md
        env: { DB_CONNECTION: mysql, DB_HOST: 127.0.0.1, DB_DATABASE: app, DB_USERNAME: root, DB_PASSWORD: '' }
      - if: always()
        run: cat preflight.md >> "$GITHUB_STEP_SUMMARY"
```

## Limitations

- Only migrations on the checked connection are analysed. Migrations that target another connection are listed in the notes.
- Migrations that read the database to decide what to do (`Schema::hasColumn` and similar) see an empty result in pretend mode, as with `migrate --pretend`.
- Editing a migration that has already run in production changes nothing there. Preflight notes such edits but analyses only new files.

## Development

```bash
composer install
vendor/bin/pest          # needs MySQL: PREFLIGHT_TEST_DB_HOST/PORT/USER/PASSWORD/DATABASE (default root@127.0.0.1, preflight_laravel_test)
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

The test database name must contain `preflight`: every table in it is dropped between tests.
