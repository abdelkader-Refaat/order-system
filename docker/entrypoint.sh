#!/bin/sh
set -e
cd /var/www/html

if [ ! -f composer.json ]; then
  echo "composer.json missing; mount the project at /var/www/html"
  exit 1
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

mkdir -p database storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
touch database/database.sqlite
chmod -R ug+rwX storage bootstrap/cache database || true

# Drop cached config from the host so container env (e.g. RABBITMQ_HOST=rabbitmq) is not replaced by 127.0.0.1.
php artisan config:clear --no-interaction 2>/dev/null || true

php artisan migrate --force --no-interaction

exec "$@"
