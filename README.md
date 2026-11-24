# Preflight

Preflight reviews database migrations in pull requests. It tells you how each statement will behave on the **real production table**: whether MySQL runs it instantly, in place, or by copying the table and blocking writes, roughly how long it takes, and how to make it safe.

MVP scope: **Laravel + MySQL 8**, delivered as a GitHub check and PR comment. The verdict uses no AI: it's a deterministic rules engine checked against real MySQL servers.

## Repository layout

| Path | What | License | Status |
|---|---|---|---|
| [`packages/core`](packages/core) | Framework-agnostic PHP engine: SQL parsing, schema snapshot, MySQL online-DDL rules, duration estimates | MIT (planned) | **M1: done** |
| `packages/laravel` | Laravel package: `php artisan preflight:check`, `preflight:stats` | MIT (planned) | M2 |
| `action/` | GitHub composite action (free tier: static verdict as a PR comment) | MIT (planned) | M3 |
| `apps/cloud/` | Laravel + Inertia React SaaS: GitHub App, size-aware verdicts, dashboard | private | M4+ |

This is one private monorepo for now. The open-source packages get published later as read-only mirrors via `git subtree split`.

The plan, architecture, data model and milestones are in [docs/PLAN.md](docs/PLAN.md).

## How it works

1. The customer's CI (GitHub Action) runs `php artisan preflight:check`. It migrates the base branch into a throwaway MySQL of the production version, snapshots `SHOW CREATE TABLE`, and captures `migrate --pretend` SQL for the new migrations. **Preflight never runs customer code on its own servers.**
2. `packages/core` analyses that SQL against the snapshot, giving a static verdict for free.
3. Paid tier: the SQL goes to Preflight Cloud (authenticated with GitHub OIDC, so no secrets are stored). The cloud joins it with production table sizes, which `php artisan preflight:stats` pushes from the customer's app on a schedule. Only numbers are sent, never row data. The result is posted back as a check run and PR comment.

## Development

```bash
cd packages/core
composer install
vendor/bin/pest
```

See [packages/core/README.md](packages/core/README.md) for the CLI, the fixture format and the MySQL oracle tests.
