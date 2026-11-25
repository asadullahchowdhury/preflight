# Getting started

Preflight tells you, on every pull request, how MySQL will run each new migration statement: instantly, in place, or by copying the whole table while writes wait. It also shows how to make risky changes safe.

It works in two layers:

- **The free layer** runs entirely in your CI: a Laravel package and a GitHub Action. Every pull request gets a comment, annotations on the migration files and a job summary.
- **Preflight Cloud (Pro)** adds the Preflight GitHub App, production table sizes with duration estimates, and a dashboard with history. It is free during the private beta.

## Requirements

- Laravel 11, 12 or 13
- MySQL 8.0 or 8.4 LTS in production (MariaDB and Postgres aren't supported yet)
- GitHub Actions

## 1. Install the package

```bash
composer require --dev preflight/laravel
```

Try it locally against your development database. It only reads: pending migrations are captured in Laravel's pretend mode, never executed.

```bash
php artisan preflight:check
```

## 2. Add the workflow

Create `.github/workflows/preflight.yml`:

```yaml
name: preflight

on:
    pull_request:
        paths: ['database/migrations/**']

permissions:
    contents: read
    pull-requests: write

jobs:
    preflight:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v4
              with:
                  fetch-depth: 0
            - uses: asadullahchowdhury/preflight/action@main
              with:
                  php-version: '8.4'
                  mysql-version: '8.0.36' # what production runs
```

Open a pull request that changes a migration, and Preflight comments on it.

## 3. Connect Preflight Cloud (optional)

1. Sign in with GitHub and install the Preflight GitHub App on the repositories you want checked.
2. Add `id-token: write` to the workflow's permissions and `cloud-url` to the action's inputs. Your repository's page in Preflight shows the exact snippet.
3. For duration estimates, push your production table sizes: see [Production table sizes](/docs/table-sizes).
