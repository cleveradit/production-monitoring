#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q '^APP_KEY=.\+' .env; then
    php artisan key:generate --force
fi

chown -R www-data:www-data storage bootstrap/cache

# No `php artisan migrate`: the dataset schema must stay untouched.
exec "$@"
