#!/usr/bin/env bash
# Sends the report to Preflight Cloud, authenticated with the job's GitHub OIDC token (no stored secrets).
# The cloud repeats the analysis and posts the check run and PR comment as the Preflight GitHub App.
# Never fails the job: if the cloud can't be reached, the action falls back to commenting itself.
set -uo pipefail

helpers="$(dirname "$0")/../bin"

warn() {
  echo "::warning title=Preflight Cloud::$1"
  echo "posted=false" >> "${GITHUB_OUTPUT:-/dev/null}"
  # The check step left annotations to the cloud; add them here instead.
  if [ "${PREFLIGHT_ANNOTATIONS:-true}" = "true" ] && [ -f "${PREFLIGHT_JSON:-}" ]; then
    GITHUB_OUTPUT=/dev/null php "$helpers/report.php" "$PREFLIGHT_JSON" "${GITHUB_WORKSPACE:-.}" true | grep '^::'
  fi
  exit 0
}

url="${PREFLIGHT_CLOUD_URL%/}"
audience="${PREFLIGHT_CLOUD_AUDIENCE:-$url}"

if [ -z "${ACTIONS_ID_TOKEN_REQUEST_URL:-}" ] || [ -z "${ACTIONS_ID_TOKEN_REQUEST_TOKEN:-}" ]; then
  warn "No OIDC token available. Add 'permissions: id-token: write' to the job. Falling back to a comment from the action."
fi

# PHP is always available here (the app runs artisan), so it handles JSON; no jq needed.
token="$(curl -sS --fail -H "Authorization: bearer $ACTIONS_ID_TOKEN_REQUEST_TOKEN" \
  "$ACTIONS_ID_TOKEN_REQUEST_URL&audience=$(php "$helpers/cloud.php" urlencode "$audience")" | php "$helpers/cloud.php" get value)"
if [ -z "$token" ]; then
  warn "Could not get an OIDC token from GitHub. Falling back to a comment from the action."
fi

payload="$(mktemp)"
php "$helpers/cloud.php" payload "$PREFLIGHT_JSON" "${PREFLIGHT_PR:-}" "${PREFLIGHT_HEAD_SHA:-}" "${PREFLIGHT_BASE_REF:-}" "${GITHUB_WORKSPACE:-}" > "$payload" \
  || warn "Could not read the report."

response="$(mktemp)"
status="$(curl -sS --max-time 150 -o "$response" -w '%{http_code}' -X POST "$url/api/analyses" \
  -H "Authorization: Bearer $token" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  --data-binary "@$payload")" || warn "Could not reach $url. Falling back to a comment from the action."

if [ "$status" != "201" ]; then
  message="$(php "$helpers/cloud.php" get message < "$response")"
  warn "Preflight Cloud answered $status${message:+: $message}. Falling back to a comment from the action."
fi

analysis_url="$(php "$helpers/cloud.php" get url < "$response")"
echo "Sent to Preflight Cloud: $analysis_url"
{
  echo "posted=true"
  echo "analysis-url=$analysis_url"
} >> "${GITHUB_OUTPUT:-/dev/null}"
