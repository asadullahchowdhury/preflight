# The GitHub Action

```yaml
- uses: asadullahchowdhury/preflight/action@main
  with:
      php-version: '8.4'
      mysql-version: '8.0.36'
```

Check out with `fetch-depth: 0` so the action can find which migrations your branch adds. Without it, the action fetches the history itself, which is slower on large repositories.

## Inputs

| Input               | Default              | What it does                                                                                                           |
| ------------------- | -------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `mysql-version`     | `8.0`                | Production's MySQL version. The action starts `mysql:<version>`; a full `x.y.z` version is also used for the analysis. |
| `start-mysql`       | `true`               | Set `false` to use a database your job already provides through `DB_*` environment variables.                          |
| `base`              | the PR's base branch | Git ref to compare with. On `push`, the previous commit.                                                               |
| `working-directory` | `.`                  | Where the Laravel app is.                                                                                              |
| `php-version`       | none                 | Set up this PHP version first.                                                                                         |
| `composer-install`  | `true`               | Run `composer install` when `vendor/` is missing.                                                                      |
| `stats-file`        | none                 | Production table sizes from `preflight:stats`, for estimates without Preflight Cloud.                                  |
| `comment`           | `auto`               | `auto` comments when something needs attention and keeps the comment current; `always`; or `never`.                    |
| `fail-on`           | `danger`             | Fail the job at `danger`, `warn` or `never`.                                                                           |
| `annotations`       | `true`               | Annotate risky lines in the migration files.                                                                           |
| `cloud-url`         | none                 | Your Preflight Cloud URL. Needs `id-token: write`.                                                                     |

## Outputs

`severity`, `danger-count`, `warn-count`, `migrations`, `markdown-file`, `json-file` and, with `cloud-url`, `analysis-url`.

```yaml
- id: preflight
  uses: asadullahchowdhury/preflight/action@main
  with:
      fail-on: never
- if: steps.preflight.outputs.severity == 'danger'
  run: echo "Needs a DBA review"
```

## Pull requests from forks

GitHub gives fork pull requests a read-only token, so the action can't comment there. The job summary and annotations still work, and the action logs a warning instead of failing.
