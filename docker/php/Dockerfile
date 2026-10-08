FROM php:8.4-fpm-bookworm@sha256:6bfef8e416977aa41f48e3e42a40c1e08050d24e4a938c6edb421400bff24601

# Dependencias de PostgreSQL, AD, hojas de cálculo, PDF y fixtures SQLite.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libldap2-dev libonig-dev libzip-dev libxml2-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql ldap mbstring gd zip \
        dom simplexml xml xmlreader xmlwriter pdo_sqlite opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs storage/app/public \
    && composer dump-autoload --optimize --no-scripts \
    && sha256sum composer.lock | cut -d' ' -f1 > vendor/.rrhh-lock

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-rrhh.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-rrhh.conf
COPY docker/php/entrypoint.sh /usr/local/bin/rrhh-entrypoint
COPY docker/php/fpm-health.php /usr/local/bin/fpm-health.php
RUN chmod +x /usr/local/bin/rrhh-entrypoint
ENTRYPOINT ["rrhh-entrypoint"]
CMD ["php-fpm", "-F"]
