#!/bin/sh
# Container entrypoint: check configuration, migrate, cache config, then run Apache in the foreground.
set -e

cd /var/www/app

# Render sets RENDER_EXTERNAL_URL; use it when APP_URL isn't set.
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is not set. Generate one with: php artisan key:generate --show (it starts with base64:)" >&2
  exit 1
fi

# Managed MySQL (e.g. Aiven) requires TLS: paste the CA certificate into DB_SSL_CA_PEM.
if [ -n "${DB_SSL_CA_PEM:-}" ]; then
  printf '%s\n' "$DB_SSL_CA_PEM" > /tmp/db-ca.pem
  export MYSQL_ATTR_SSL_CA=/tmp/db-ca.pem
fi

# Apache listens on the port the host assigns.
sed -i "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf

php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
