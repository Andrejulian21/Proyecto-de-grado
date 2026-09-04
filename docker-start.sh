#!/bin/sh
# Start PHP-FPM in background
php-fpm -D --fpm-config /usr/local/etc/php-fpm.d/www.conf

# Wait for FPM socket to be ready
sleep 2

# Start Nginx in foreground
exec nginx -g "daemon off;"
