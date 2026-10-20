#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ "${APP_ENV:-production}" = "local" ] && [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies for development..."
    composer install --no-interaction --prefer-dist
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    wait-for-db
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ ! -L public/storage ]; then
    php artisan storage:link >/dev/null 2>&1 || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache >/dev/null 2>&1 || true
    php artisan view:cache >/dev/null 2>&1 || true
fi

exec "$@"
