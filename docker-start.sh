#!/bin/sh
# Ensure storage directories exist in the mounted volume
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Create symlink at RUNTIME (after volume mount)
ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

php-fpm -D
sleep 1
nginx -t && nginx -g "daemon off;"
