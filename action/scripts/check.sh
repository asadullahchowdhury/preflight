#!/usr/bin/env bash
# Runs `php artisan preflight:check` for the branch and writes the reports, job summary, annotations and outputs.
set -uo pipefail

fail() {
  echo "::error title=Preflight::$1"
  exit 2
}

out_dir="${RUNNER_TEMP:-/tmp}/preflight"
mkdir -p "$out_dir"
markdown="$out_dir/preflight.md"
json="$out_dir/preflight.json"
rm -f "$markdown" "$json"

# 1. Dependencies.
if [ ! -f artisan ]; then
  fail "No artisan file in $(pwd). Set working-directory to your Laravel app."
fi
if [ ! -f vendor/autoload.php ] && [ "${PREFLIGHT_COMPOSER_INSTALL:-true}" = "true" ]; then
  echo "::group::composer install"
  composer install --no-interaction --no-progress --prefer-dist || fail "composer install failed."
  echo "::endgroup::"
fi
if ! php artisan list --raw 2>/dev/null | grep -q '^preflight:check'; then
  fail "preflight/laravel is not installed in this app. Run: composer require --dev preflight/laravel"
fi

# 2. The base to compare with.
base="${PREFLIGHT_BASE:-}"
if [ -z "$base" ] && [ -n "${PREFLIGHT_BASE_REF:-}" ]; then
  base="origin/$PREFLIGHT_BASE_REF"
fi
if [ -z "$base" ] && [ -n "${PREFLIGHT_BEFORE:-}" ] && [[ ! "$PREFLIGHT_BEFORE" =~ ^0+$ ]]; then
  base="$PREFLIGHT_BEFORE"
fi
if [ -z "$base" ]; then
  fail "Could not tell what to compare with. Run on pull_request or push, or set the 'base' input."
fi

# Make sure the base and the merge base are available, even after a shallow checkout.
if [[ "$base" == origin/* ]]; then
  branch="${base#origin/}"
  git fetch --no-tags --quiet origin "+refs/heads/$branch:refs/remotes/origin/$branch" || fail "Could not fetch the base branch '$branch'."
elif ! git cat-file -e "$base^{commit}" 2>/dev/null; then
  git fetch --no-tags --quiet origin "$base" 2>/dev/null || true
fi
if ! git merge-base "$base" HEAD >/dev/null 2>&1; then
  if [ "$(git rev-parse --is-shallow-repository)" = "true" ]; then
    echo "Shallow checkout: fetching history to find the merge base (use actions/checkout with fetch-depth: 0 to skip this)."
    git fetch --no-tags --quiet --unshallow origin || fail "Could not fetch the repository history."
    if [[ "$base" == origin/* ]]; then
      git fetch --no-tags --quiet origin "+refs/heads/${base#origin/}:refs/remotes/origin/${base#origin/}" || true
    fi
  fi
  git merge-base "$base" HEAD >/dev/null 2>&1 || fail "No common history between '$base' and HEAD."
fi

# 3. Database: the container started by this action, or whatever the job configured.
if [ -n "${PREFLIGHT_DB_PORT:-}" ]; then
  export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$PREFLIGHT_DB_PORT" DB_DATABASE=preflight DB_USERNAME=root DB_PASSWORD=
fi

args=(--base="$base" --markdown="$markdown" --json="$json" --fail-on=never --ansi)
if [[ "${PREFLIGHT_MYSQL_VERSION:-}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  args+=(--mysql="$PREFLIGHT_MYSQL_VERSION")
fi
if [ -n "${PREFLIGHT_STATS_FILE:-}" ]; then
  args+=(--stats="$PREFLIGHT_STATS_FILE")
fi

# 4. Check.
php artisan preflight:check "${args[@]}"
code=$?
if [ $code -ne 0 ] || [ ! -f "$json" ]; then
  fail "php artisan preflight:check failed (exit code $code)."
fi

# 5. Job summary, annotations, outputs.
cat "$markdown" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
php "$(dirname "$0")/../bin/report.php" "$json" "${GITHUB_WORKSPACE:-$(git rev-parse --show-toplevel)}" "${PREFLIGHT_ANNOTATIONS:-true}"
{
  echo "markdown-file=$markdown"
  echo "json-file=$json"
} >> "${GITHUB_OUTPUT:-/dev/null}"
