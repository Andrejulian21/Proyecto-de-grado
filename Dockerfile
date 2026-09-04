# syntax=docker/dockerfile:1

# ------------------------------------------------------------------------------
# Production image for the Proyectos de Grado platform.
#
# Multi-stage build:
#   1. frontend — Node 22 + pnpm, builds the React SPA into public/build
#   2. vendor   — PHP 8.4-fpm, installs prod deps
#   3. runtime  — PHP-FPM + Nginx (serves static + proxies to FPM)
# ------------------------------------------------------------------------------

# ---------------------------------------------------------------- frontend ----
FROM node:22-alpine AS frontend

WORKDIR /app

RUN npm install -g pnpm@11

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm run build

# ----------------------------------------------------------------- vendor ----
FROM php:8.4-fpm-alpine AS vendor

RUN apk add --no-cache $PHPIZE_DEPS \
        postgresql-dev libzip-dev oniguruma-dev icu-dev libxml2-dev \
        libpng-dev freetype-dev libjpeg-turbo-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql bcmath opcache pcntl zip gd intl mbstring xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader --no-scripts --no-progress

COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi

# --------------------------------------------------------------- runtime -----
FROM php:8.4-fpm-alpine AS runtime

# PHP extensions + Nginx
RUN apk add --no-cache $PHPIZE_DEPS \
        postgresql-dev libzip-dev oniguruma-dev icu-dev libxml2-dev \
        libpng-dev freetype-dev libjpeg-turbo-dev nginx \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql bcmath opcache pcntl zip gd intl mbstring xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

# PHP production config
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'expose_php=0'; \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/99-production.ini

# Nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf
RUN rm -f /etc/nginx/http.d/default.conf.bak

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# Laravel needs write access to storage and bootstrap cache
RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV APP_ENV=production

EXPOSE 80

# Startup script: start PHP-FPM in background, then Nginx in foreground
COPY docker-start.sh /start.sh
RUN chmod +x /start.sh

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://localhost/api/health || exit 1

CMD ["/start.sh"]
