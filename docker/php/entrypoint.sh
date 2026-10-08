#!/bin/sh
set -eu
cd /var/www/html
lock=$(sha256sum composer.lock | cut -d' ' -f1)
if [ ! -f vendor/.rrhh-lock ] || [ "$(cat vendor/.rrhh-lock)" != "$lock" ]; then
    composer install --no-interaction --prefer-dist --no-scripts --optimize-autoloader
    printf '%s\n' "$lock" > vendor/.rrhh-lock
fi
mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs storage/app/public
chown -R www-data:www-data bootstrap/cache storage/framework storage/logs
# Nunca compartir config cache nativa ni migrar/sembrar al arrancar.
php artisan config:clear
php artisan package:discover --ansi
exec docker-php-entrypoint "$@"
