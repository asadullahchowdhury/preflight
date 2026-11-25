# Deploying Preflight Cloud for free

This guide puts Preflight Cloud on the internet at no cost:

- **Render** runs the app (free web service, built from `apps/cloud/Dockerfile`).
- **Aiven** provides the MySQL database (free plan).

Allow about 30 minutes. You need the Render, Aiven and GitHub accounts yourself; nothing here can be done for you.

> **What "free" means here.** Render's free service sleeps after about 15 minutes without traffic; the next request takes 30–60 seconds while it wakes. GitHub webhooks that arrive while it's asleep may time out. You can re-send them from the GitHub App's *Advanced* page, and installations also sync when you sign in or click *Sync with GitHub*. Free plans and their limits change, so check Render's and Aiven's current terms.

## 1. Database: Aiven MySQL

1. Sign up at https://aiven.io and create a **MySQL** service on the **Free** plan. Pick a region close to your Render region.
2. When it's running, open the service's **Overview** and note: **Host**, **Port**, **User** (`avnadmin`), **Password** and **Database name** (`defaultdb`).
3. Download the **CA certificate** (`ca.pem`) from the same page. Aiven only accepts encrypted connections, and the app needs this file's text.

## 2. An application key

On your computer, in `apps/cloud`:

```powershell
php artisan key:generate --show
```

Copy the output (it starts with `base64:`). It encrypts sessions and the GitHub tokens stored in the database, so keep it secret, and keep it the same across deploys.

## 3. The app: Render

1. Sign up at https://render.com with GitHub, and allow it access to the `preflight` repository (private repositories work).
2. Choose **New → Blueprint** and select the repository. Render reads `render.yaml` and proposes one free web service, `preflight-cloud`.
3. Fill in the values it asks for:

   | Variable | Value |
   |---|---|
   | `APP_KEY` | the `base64:` key from step 2 |
   | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from Aiven |
   | `DB_SSL_CA_PEM` | the full text of `ca.pem`, including the `-----BEGIN CERTIFICATE-----` lines |
   | `GITHUB_*` | leave empty for now |

4. Click **Apply**. The first build takes a few minutes. On start, the container runs the database migrations itself.
5. Open the URL Render shows (like `https://preflight-cloud-xxxx.onrender.com`). You should see the landing page. Sign-in says GitHub isn't configured yet; that comes next.

## 4. The GitHub App

Create it at https://github.com/settings/apps/new, or under your organization's settings. Replace `URL` below with your Render URL.

| Setting | Value |
|---|---|
| GitHub App name | anything unique, e.g. `preflight-yourname`; its URL slug becomes `GITHUB_APP_SLUG` |
| Homepage URL | `URL` |
| Callback URL | `URL/auth/github/callback` |
| Setup URL | `URL/github/setup`, with **Redirect on update** ticked |
| Webhook URL | `URL/github/webhook` |
| Webhook secret | a long random string (also goes into `GITHUB_WEBHOOK_SECRET`) |
| Repository permissions | **Checks**: Read and write · **Pull requests**: Read and write · **Metadata**: Read-only |
| Subscribe to events | Installation target; *installation* events are always sent |
| Where can it be installed | *Only on this account* during the beta |

Then, on the App's page:

1. Note the **App ID** and the **Client ID**, and generate a **client secret**.
2. Under **Private keys**, generate a key; a `.pem` file downloads.

Back in Render, open the service's **Environment** and set:

| Variable | Value |
|---|---|
| `GITHUB_APP_ID` | the App ID |
| `GITHUB_APP_SLUG` | the App's slug (from its public URL `github.com/apps/<slug>`) |
| `GITHUB_APP_PRIVATE_KEY` | the full text of the `.pem` file (or one line with `\n` in place of newlines) |
| `GITHUB_WEBHOOK_SECRET` | the webhook secret |
| `GITHUB_CLIENT_ID` / `GITHUB_CLIENT_SECRET` | the client ID and secret |

Saving redeploys the service.

## 5. Try it

1. Open your Render URL, choose **Sign in**, then **Continue with GitHub**.
2. On the dashboard, **Add repositories** installs the App. Choose a repository that has Laravel migrations.
3. While the `preflight` repository is private, other repositories can only use its Action once you allow it: in `preflight` → **Settings → Actions → General → Access**, choose *Accessible from repositories owned by the user*.
4. In that repository, require the package and add the workflow shown on its page in Preflight. Its `cloud-url` is already your Render URL:

   ```yaml
   permissions:
     contents: read
     id-token: write
   ```

5. Open a pull request that changes a migration. The check run and comment come from your App.
6. Optional: on the repository's **Table sizes** tab, create a stats token and schedule `php artisan preflight:stats --push` in production for size-aware checks.

## Updating

Pushing to `main` redeploys automatically when files under `apps/cloud` or `packages/core/src` change (see `buildFilter` in `render.yaml`). Migrations run on every start.

## Other hosts

The same image runs anywhere that runs Docker with `$PORT`, such as Koyeb or a small VPS. Build it from the repository root:

```bash
docker build -f apps/cloud/Dockerfile -t preflight-cloud .
docker run -p 8080:8080 -e APP_KEY=base64:… -e DB_HOST=… -e DB_DATABASE=… -e DB_USERNAME=… -e DB_PASSWORD=… preflight-cloud
```

The image sets `QUEUE_CONNECTION=sync`, so it needs no separate worker: GitHub posts happen right after each API response.
