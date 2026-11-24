#!/usr/bin/env bash
# Starts a throwaway MySQL container for building the base schema. Writes the port to $GITHUB_OUTPUT.
set -euo pipefail

version="${PREFLIGHT_MYSQL_VERSION:-8.0}"
if [[ ! "$version" =~ ^[0-9]+(\.[0-9]+){0,2}$ ]]; then
  echo "::error title=Preflight::mysql-version must look like 8.0 or 8.0.36, got '$version'."
  exit 2
fi

name="preflight-mysql"
port=33306

docker rm -f "$name" >/dev/null 2>&1 || true
echo "Starting mysql:$version on port $port…"
docker run -d --name "$name" -p "127.0.0.1:$port:3306" \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=preflight \
  "mysql:$version" >/dev/null

# The image first runs a temporary server without networking while it initialises, so wait for TCP.
for _ in $(seq 1 90); do
  if docker exec "$name" mysql --protocol=TCP -h127.0.0.1 -uroot -e 'select 1' >/dev/null 2>&1; then
    echo "MySQL $(docker exec "$name" mysql --protocol=TCP -h127.0.0.1 -uroot -N -e 'select version()') is ready."
    echo "port=$port" >> "$GITHUB_OUTPUT"
    exit 0
  fi
  sleep 2
done

echo "::error title=Preflight::MySQL $version did not become ready in 180s."
docker logs "$name" 2>&1 | tail -20
exit 2
