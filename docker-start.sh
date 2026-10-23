#!/bin/sh
php artisan storage:link --force
php-fpm -D
sleep 1
nginx -t && nginx -g "daemon off;"
