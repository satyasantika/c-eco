#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Image produksi tidak di-bind dari host; www-data harus bisa menulis storage.
if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache || true
fi
chmod -R 775 storage bootstrap/cache

keyfile=storage/app/.deploy_app_key
if [ -z "${APP_KEY:-}" ]; then
    if [ -f "$keyfile" ]; then
        APP_KEY="$(cat "$keyfile")"
    elif [ "${1:-}" = "php-fpm" ]; then
        APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
        printf '%s' "$APP_KEY" > "$keyfile"
    else
        i=0
        while [ ! -f "$keyfile" ] && [ "$i" -lt 30 ]; do
            sleep 1
            i=$((i + 1))
        done
        APP_KEY="$(cat "$keyfile")"
    fi
    export APP_KEY
fi

if [ "${1:-}" = "php-fpm" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
