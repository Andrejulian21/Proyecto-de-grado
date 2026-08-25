# syntax=docker/dockerfile:1

# ------------------------------------------------------------------------------
# Production image for the Proyectos de Grado platform (issue #55).
#
# Multi-stage build:
#   1. frontend — Node 20 + pnpm (matches CI), builds the React SPA into public/build
#   2. vendor   — PHP 8.4 (matches composer.json platform + CI), installs prod deps
#   3. runtime  — slim php-fpm image with only what the app needs at runtime
#
# Deploy target: Docker Compose on a single Azure VM (see openspec/config.yaml).
# ------------------------------------------------------------------------------

# ---------------------------------------------------------------- frontend ----
FROM node:20-alpine AS frontend

WORKDIR /app

RUN npm install -g pnpm@11

# Dependency layer first, so it only rebuilds when the lockfile changes.
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm run build

# ----------------------------------------------------------------- vendor ----
FROM php:8.4-fpm-alpine AS vendor

# Build deps + libs required by the PHP extensions the app needs at runtime
# (pdo_pgsql, gd, zip, intl, mbstring, xml group, redis).
RUN apk add --no-cache $PHPIZE_DEPS \
        postgresql-dev libzip-dev oniguruma-dev icu-dev libxml2-dev \
        libpng-dev freetype-dev libjpeg-turbo-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql bcmath opcache pcntl zip gd intl mbstring xml \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Production dependencies only. --no-scripts: the app is not copied yet, so
# package discovery would fail here; it runs explicitly after the full copy.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader --no-scripts --no-progress

COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi

# --------------------------------------------------------------- runtime -----
FROM php:8.4-fpm-alpine AS runtime

# Same extension set as the vendor stage; build-only tools are removed after.
RUN apk add --no-cache $PHPIZE_DEPS \
        postgresql-dev libzip-dev oniguruma-dev icu-dev libxml2-dev \
        libpng-dev freetype-dev libjpeg-turbo-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql bcmath opcache pcntl zip gd intl mbstring xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

# Production hardening: opcache with timestamp validation off, hide PHP version.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'expose_php=0'; \
    } > /usr/local/etc/php/conf.d/99-production.ini

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# Laravel needs write access to storage and the bootstrap cache at runtime.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Overridable via Compose env; the real .env is provided at deploy time.
ENV APP_ENV=production

EXPOSE 9000

# php-fpm runs its workers as www-data. The health check validates the FPM
# config/process; app-level liveness is checked by the nginx proxy.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm"]
