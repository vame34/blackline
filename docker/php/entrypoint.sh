#!/usr/bin/env sh
set -e

load_secret() {
    var="$1"
    file_var="${var}_FILE"
    file="$(eval "printf '%s' \"\${$file_var:-}\"")"

    if [ -n "$file" ] && [ -f "$file" ]; then
        export "$var"="$(cat "$file")"
    fi
}

load_secret "APP_KEY"
load_secret "DB_PASSWORD"
load_secret "DB_ROOT_PASSWORD"
load_secret "REDIS_PASSWORD"
load_secret "MAIL_PASSWORD"

mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
