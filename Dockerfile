# syntax=docker/dockerfile:1

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

# Install PHP extensions + Nginx + startup deps
RUN apk add --no-cache $PHPIZE_DEPS \
        postgresql-dev libzip-dev oniguruma-dev icu-dev libxml2-dev \
        libpng-dev freetype-dev libjpeg-turbo-dev nginx bash \
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

# Nginx config — inline to avoid COPY issues
RUN rm -f /etc/nginx/http.d/default.conf /etc/nginx/http.d/default.conf.bak && \
    cat > /etc/nginx/http.d/default.conf << 'NGINX_EOF'
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 256;
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    location /api { try_files $uri $uri/ /index.php?$query_string; }
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    location ~ /\. { deny all; }
}
NGINX_EOF

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV APP_ENV=production
EXPOSE 80

# Startup: FPM in background, Nginx in foreground
CMD ["bash", "-c", "php-fpm -D && nginx -g 'daemon off;'"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://localhost/api/health || exit 1
