#!/usr/bin/env bash
# Run the core oracle suite against several MySQL versions in Docker.
#
#   scripts/oracle-matrix.sh                 # default versions
#   scripts/oracle-matrix.sh 8.0.28 8.4      # specific versions
#
# Each version gets a throwaway container (removed afterwards) with an empty root password and a
# `preflight_oracle` database. Needs docker and PHP with pdo_mysql; run `composer install` in packages/core first.

set -uo pipefail

VERSIONS=("$@")
if [ ${#VERSIONS[@]} -eq 0 ]; then
  # Versions where online-DDL behaviour changes: INSTANT arrives (8.0.12), INSTANT rename (8.0.28),
  # INSTANT add-anywhere/drop (8.0.29, withdrawn; 8.0.30 is the first usable), latest 8.0, 8.4 LTS.
  VERSIONS=(8.0.12 8.0.27 8.0.28 8.0.30 8.0 8.4)
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CORE="$ROOT/packages/core"
PORT=33061
declare -A RESULT

cleanup() { docker rm -f "$1" >/dev/null 2>&1 || true; }

for version in "${VERSIONS[@]}"; do
  name="preflight-oracle-${version//./-}"
  echo "=== MySQL $version ==="
  cleanup "$name"

  if ! docker run -d --name "$name" -p "127.0.0.1:$PORT:3306" \
      -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=preflight_oracle \
      "mysql:$version" >/dev/null; then
    RESULT[$version]="could not start"
    continue
  fi

  ready=0
  for _ in $(seq 1 90); do
    if php -r 'try { new PDO("mysql:host=127.0.0.1;port='"$PORT"';dbname=preflight_oracle", "root", ""); exit(0); } catch (Throwable $e) { exit(1); }' 2>/dev/null; then
      ready=1
      break
    fi
    sleep 2
  done

  if [ $ready -eq 0 ]; then
    RESULT[$version]="not ready after 180s"
    cleanup "$name"
    continue
  fi

  if (cd "$CORE" && PREFLIGHT_ORACLE_DSN="mysql:host=127.0.0.1;port=$PORT;dbname=preflight_oracle" vendor/bin/pest --group=oracle); then
    RESULT[$version]="pass"
  else
    RESULT[$version]="FAIL"
  fi
  cleanup "$name"
done

echo
echo "=== Summary ==="
status=0
for version in "${VERSIONS[@]}"; do
  printf '  MySQL %-8s %s\n' "$version" "${RESULT[$version]}"
  [ "${RESULT[$version]}" = "pass" ] || status=1
done
exit $status
