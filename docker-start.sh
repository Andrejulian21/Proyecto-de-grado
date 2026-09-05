#!/bin/sh
php-fpm -D
sleep 1
nginx -t && nginx -g "daemon off;"
