#!/usr/bin/env bash
set -Eeuo pipefail

PORT_VALUE="${PORT:-10000}"
if [[ ! "${PORT_VALUE}" =~ ^[0-9]+$ ]]; then
    echo "PORT must be numeric." >&2
    exit 1
fi

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Remove only generated framework caches from the image. No application data
# or uploaded evidence is deleted here.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

php artisan migrate --force

if [[ "${RUN_PRODUCTION_SEEDERS:-false}" == "true" ]]; then
    php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force
fi

if [[ "${RUN_FULL_DEMO_SEEDERS:-false}" == "true" ]]; then
    php artisan db:seed --class=Database\\Seeders\\RenderDemoSeeder --force
fi

if [[ "${BOOTSTRAP_ADMIN_ENABLED:-false}" == "true" ]]; then
    php artisan agis:bootstrap-admin
fi

php artisan config:cache
php artisan view:cache

# Keep the deployment gate explicit so local Docker checks can continue to use
# SQLite and an HTTP URL. Render production should set ARMIS_DEPLOYMENT_CHECK=true.
if [[ "${ARMIS_DEPLOYMENT_CHECK:-false}" == "true" ]]; then
    php artisan armis:deployment-check --strict
fi

sed -ri "s/^Listen [0-9]+$/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s#<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT_VALUE}>#" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
