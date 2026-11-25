# Preflight Cloud: one container with Apache + PHP 8.4, for free hosts such as Render or Koyeb.
# Build from the repository root (the app uses the local packages/core):
#
#   docker build -f apps/cloud/Dockerfile -t preflight-cloud .
#   docker run -p 8080:8080 -e APP_KEY=base64:... -e DB_HOST=... preflight-cloud

# ---- Build: PHP dependencies, typed routes and front-end assets ------------------------------------------
FROM php:8.4-cli-bookworm AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev \
    && docker-php-ext-install intl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Node, for building the assets (the Vite plugin also runs `php artisan wayfinder:generate`).
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /app
COPY packages/core packages/core
COPY apps/cloud/composer.json apps/cloud/composer.lock apps/cloud/

# Copy packages/core into vendor instead of symlinking it, so vendor works on its own.
ENV COMPOSER_MIRROR_PATH_REPOS=1
RUN cd apps/cloud && composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader

COPY apps/cloud/package.json apps/cloud/package-lock.json apps/cloud/
RUN cd apps/cloud && npm ci --no-audit --no-fund

COPY apps/cloud apps/cloud
RUN cd apps/cloud \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && php artisan wayfinder:generate --with-form \
    && npm run build \
    && rm -rf node_modules .env storage/logs/* bootstrap/cache/*.php

# ---- Runtime: Apache + PHP, serving apps/cloud/public ------------------------------------------------------
FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu72 libicu-dev \
    && docker-php-ext-install intl pdo_mysql opcache \
    && apt-get purge -y libicu-dev && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

COPY apps/cloud/docker/php.ini /usr/local/etc/php/conf.d/preflight.ini
COPY apps/cloud/docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY apps/cloud/docker/start.sh /usr/local/bin/preflight-start
RUN chmod +x /usr/local/bin/preflight-start

WORKDIR /var/www/app
COPY --from=build --chown=www-data:www-data /app/apps/cloud /var/www/app

# Hosts tell the container which port to listen on with $PORT (Render: 10000, Koyeb: 8000).
ENV PORT=8080 \
    APP_NAME=Preflight \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    TRUSTED_PROXIES=* \
    QUEUE_CONNECTION=sync \
    SESSION_DRIVER=database \
    CACHE_STORE=database

EXPOSE 8080
CMD ["preflight-start"]
