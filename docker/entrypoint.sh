#!/bin/sh
set -e

mkdir -p /var/www/html/var/cache /var/www/html/var/log
chmod -R 777 /var/www/html/var 2>/dev/null || true

exec "$@"

