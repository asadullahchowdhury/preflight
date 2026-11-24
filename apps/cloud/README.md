# Preflight Cloud

The hosted side of Preflight: a Laravel 13 + Inertia React app that

- signs people in with GitHub,
- tracks where the **Preflight GitHub App** is installed (install flow and webhooks),
- receives reports from CI at `POST /api/analyses`, authenticated with the job's **GitHub OIDC token** (no secrets to store),
- **re-runs the analysis** with `preflight/core` (the same engine as `preflight:check`, and where production table sizes plug in later),
- posts a **check run** (with annotations) and **one PR comment** as the App,
- shows repositories, checks and findings in a dashboard.

## Run it locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# MySQL: create the database named in DB_DATABASE (default preflight_cloud)
php artisan migrate
composer run dev            # or: php artisan serve + npm run dev
```

**No GitHub App yet?** Set `PREFLIGHT_DEV_LOGIN=true` (with `APP_ENV=local`), open `/login` and choose **Explore the demo**. That signs you in as a demo user with seeded repositories and checks produced by the real engine. The route doesn't exist outside `APP_ENV=local`.

## The GitHub App

Create it at https://github.com/settings/apps/new (or under an organization).

| Setting                | Value                                                                                                |
| ---------------------- | ---------------------------------------------------------------------------------------------------- |
| Homepage URL           | `APP_URL`                                                                                            |
| Callback URL           | `APP_URL/auth/github/callback` (enable _Request user authorization during installation_ if you like) |
| Setup URL              | `APP_URL/github/setup` (enable _Redirect on update_)                                                 |
| Webhook URL            | `APP_URL/github/webhook`, with a secret                                                              |
| Repository permissions | **Checks**: read & write · **Pull requests**: read & write · **Metadata**: read                      |
| Events                 | Installation, Installation repositories (installation events are always sent)                        |

Then fill `.env`:

```dotenv
GITHUB_APP_ID=123456
GITHUB_APP_SLUG=your-app-slug
GITHUB_APP_PRIVATE_KEY=/path/to/app.private-key.pem   # or the PEM itself, with \n for newlines
GITHUB_WEBHOOK_SECRET=…
GITHUB_CLIENT_ID=Iv1.…                                 # the App's OAuth client: used for "Sign in with GitHub"
GITHUB_CLIENT_SECRET=…
```

## CI → cloud

In a repository with the App installed:

```yaml
permissions:
    contents: read
    id-token: write

steps:
    - uses: actions/checkout@v4
      with: { fetch-depth: 0 }
    - uses: asadullahchowdhury/preflight/action@main
      with:
          mysql-version: '8.0.36'
          cloud-url: https://your-preflight-host
```

The action requests an OIDC token for the audience `cloud-url`, which must equal `PREFLIGHT_OIDC_AUDIENCE` here (default `APP_URL`). The API checks:

- the signature, against GitHub's published keys;
- the issuer, audience and expiry;
- that the `repository_id` claim belongs to an active installation.

The PR number comes from the token's `ref` claim, not from the request body.

If the cloud can't be reached, the action falls back to commenting itself, so CI never depends on the cloud being up.

## Tests

```bash
php artisan test          # SQLite in memory; GitHub's API and OIDC provider are faked
vendor/bin/phpstan analyse
vendor/bin/pint --test
npx vp check && npx tsc --noEmit
```

`tests/Fixtures/*.pem` are throwaway keys used only to sign fake tokens in tests.

## Layout

| Path                              | What                                                            |
| --------------------------------- | --------------------------------------------------------------- |
| `app/GitHub/GitHubApp.php`        | API client as the App (JWT) or an installation (cached tokens)  |
| `app/GitHub/OidcVerifier.php`     | Verifies GitHub Actions OIDC tokens                             |
| `app/GitHub/InstallationSync.php` | Installations and repositories, from a user's token or webhooks |
| `app/Analysis/AnalysisRunner.php` | Re-runs `preflight/core` on what CI sent and stores the result  |
| `app/Jobs/PublishAnalysis.php`    | Check run and PR comment as the App                             |
| `resources/js/pages`              | Landing, sign-in, dashboard, repository and analysis pages      |
