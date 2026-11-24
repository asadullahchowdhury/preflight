# Preflight GitHub Action

Flags Laravel migrations that lock or copy MySQL tables before they're merged. Each pull request gets:

- **a PR comment** saying how MySQL will run each new statement (INSTANT / INPLACE / COPY), whether it blocks writes, and a safer alternative;
- **annotations** on the risky lines of the migration files, in the PR's diff;
- **a job summary** with the same report;
- **a failing check** when something is rated `danger` (configurable).

It runs `php artisan preflight:check` from [`preflight/laravel`](../packages/laravel) in your CI:

1. Start a throwaway MySQL container of your production version.
2. Run the base branch's migrations to rebuild the production schema.
3. Capture the SQL of the migrations your branch adds, in pretend mode, so nothing runs.

There's no account and no AI, and your code doesn't leave the runner.

## Setup

```bash
composer require --dev preflight/laravel
```

```yaml
# .github/workflows/preflight.yml
name: preflight

on:
  pull_request:
    paths: ['database/migrations/**']

permissions:
  contents: read
  pull-requests: write   # for the PR comment

jobs:
  preflight:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0     # git history, to find which migrations are new

      - uses: OWNER/preflight/action@v1   # placeholder until the action is published
        with:
          php-version: '8.4'
          mysql-version: '8.0.36'   # what production runs
```

The action runs `composer install` itself if `vendor/` is missing. If your job already sets up PHP and dependencies, leave out `php-version`.

## Inputs

| Input | Default | |
|---|---|---|
| `mysql-version` | `8.0` | Production's MySQL version. The action starts `mysql:<version>`. A full `x.y.z` version is also used for the analysis, which matters because online DDL changes between minor versions. |
| `start-mysql` | `true` | `false` uses a database your job already provides through `DB_*` env vars (e.g. a `services:` container). |
| `base` | the PR's base branch | Git ref to compare with. On `push`, the default is the previous commit. |
| `working-directory` | `.` | Where the Laravel app is. |
| `php-version` | none | Set up PHP first with `shivammathur/setup-php`. |
| `composer-install` | `true` | Run `composer install` when `vendor/` is missing. |
| `stats-file` | none | Production table sizes from `php artisan preflight:stats`. Adds duration estimates and makes severity size-aware. |
| `comment` | `auto` | `auto` comments when there's something to flag and keeps an existing comment current. `always` always comments. `never` uses only the job summary. |
| `fail-on` | `danger` | Fail the job at `danger`, `warn`, or `never`. |
| `annotations` | `true` | Annotate risky lines in the migration files. |
| `github-token` | `github.token` | Token for the comment. |

## Outputs

`severity` (`info` / `warn` / `danger`), `danger-count`, `warn-count`, `migrations`, `markdown-file`, `json-file`.

```yaml
      - id: preflight
        uses: OWNER/preflight/action@v1
        with: { fail-on: never }
      - if: steps.preflight.outputs.severity == 'danger'
        run: echo "Needs a DBA review"
```

## Notes

- **Fork PRs.** GitHub gives fork PRs a read-only token, so the comment can't be posted. The job summary and annotations still work, and the action logs a warning instead of failing.
- **Shallow checkouts.** Without `fetch-depth: 0`, the action fetches the history it needs itself. That's slower on big repositories.
- **One comment per PR.** The comment is found by a hidden `<!-- preflight-report -->` marker and updated in place on every push.
- **Production sizes.** Store the output of `php artisan preflight:stats` (run in production) somewhere CI can read it, and pass it as `stats-file`. Then a COPY on a 2,000-row table is `info` and the same COPY on 40M rows is `danger`, with an estimate.

## Development

The action is plain bash (`scripts/`) plus a dependency-free PHP helper (`bin/report.php`) that turns the JSON report into annotations and outputs.

`.github/workflows/action.yml` tests the action end to end. `tests/make-app.sh` builds a fresh Laravel app whose `feature` branch adds the migrations in `tests/migrations`. The workflow then runs `uses: ./action` on it, on MySQL 8.0.36 and 8.4.
