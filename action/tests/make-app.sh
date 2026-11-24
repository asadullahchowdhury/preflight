#!/usr/bin/env bash
# Builds a throwaway Laravel app for testing the action end to end:
#   - a fresh laravel/laravel with preflight/laravel installed from this repository (path repositories),
#   - its own git repository: the skeleton committed on "main",
#   - a "feature" branch adding the migrations in action/tests/migrations.
#
#   action/tests/make-app.sh /path/to/app
set -euo pipefail

app="${1:?Usage: make-app.sh /path/to/app}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
# Git Bash on Windows: Composer (a Windows program) needs D:/… rather than /d/….
if command -v cygpath > /dev/null; then root="$(cygpath -m "$root")"; fi
git_id=(-c user.name=preflight -c user.email=preflight@example.test -c commit.gpgsign=false)

rm -rf "$app"
composer create-project laravel/laravel "$app" --no-interaction --no-progress --no-scripts --quiet
cd "$app"

composer config repositories.preflight-core \
  "{\"type\": \"path\", \"url\": \"$root/packages/core\", \"options\": {\"versions\": {\"preflight/core\": \"0.1.x-dev\"}}}"
composer config repositories.preflight-laravel \
  "{\"type\": \"path\", \"url\": \"$root/packages/laravel\", \"options\": {\"versions\": {\"preflight/laravel\": \"0.1.x-dev\"}}}"
composer config minimum-stability dev
composer config prefer-stable true
composer require --dev "preflight/laravel:0.1.x-dev" --no-interaction --no-progress --quiet

cp .env.example .env
php artisan key:generate --quiet

git init --quiet --initial-branch=main
git add -A
git "${git_id[@]}" commit --quiet -m "Laravel skeleton"

git checkout --quiet -b feature
cp "$root/action/tests/migrations/"*.php database/migrations/
git add -A
git "${git_id[@]}" commit --quiet -m "Add migrations"

echo "Laravel app with a feature branch ready at $app"
