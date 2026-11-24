#!/usr/bin/env bash
# Creates or updates the Preflight comment on the pull request. Never fails the job: on fork PRs the token is
# read-only, and the job summary has the same report.
set -uo pipefail

marker='<!-- preflight-report -->'
repo="${GITHUB_REPOSITORY:?}"
pr="${PREFLIGHT_PR:?}"
mode="${PREFLIGHT_COMMENT:-auto}"
severity="${PREFLIGHT_SEVERITY:-info}"

body="$(mktemp)"
# GitHub comments are limited to 65,536 characters.
if [ "$(wc -c < "$PREFLIGHT_MARKDOWN")" -gt 60000 ]; then
  {
    head -c 58000 "$PREFLIGHT_MARKDOWN"
    printf '\n\n…\n\n> The report is too long for a comment. The full version is in the job summary.\n'
  } > "$body"
else
  cp "$PREFLIGHT_MARKDOWN" "$body"
fi

if ! ids="$(gh api "repos/$repo/issues/$pr/comments" --paginate \
  --jq ".[] | select(.body | startswith(\"$marker\")) | .id" 2>/dev/null)"; then
  echo "::warning title=Preflight::Could not read the PR's comments (token permissions?). The report is in the job summary."
  exit 0
fi
existing="$(printf '%s\n' "$ids" | grep -E '^[0-9]+$' | head -n 1 || true)"

if [ -n "$existing" ]; then
  if gh api -X PATCH "repos/$repo/issues/comments/$existing" -F "body=@$body" >/dev/null; then
    echo "Updated the Preflight comment."
  else
    echo "::warning title=Preflight::Could not update the PR comment (token permissions?). The report is in the job summary."
  fi
  exit 0
fi

if [ "$mode" = "auto" ] && [ "$severity" = "info" ]; then
  echo "Nothing to flag; no comment posted."
  exit 0
fi

if gh api "repos/$repo/issues/$pr/comments" -F "body=@$body" >/dev/null; then
  echo "Posted the Preflight comment."
else
  echo "::warning title=Preflight::Could not comment on the PR. Fork PRs get a read-only token; otherwise give the job 'pull-requests: write'. The report is in the job summary."
fi
exit 0
