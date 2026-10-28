FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    postgresql-client \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_pgsql pgsql zip \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-wecar.ini
COPY docker/php/wait-for-db.sh /usr/local/bin/wait-for-db
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/wait-for-db /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]

# ---------------------------------------------------------------
# Desarrollo: código bind-mounted, vendor y node_modules del host
# ---------------------------------------------------------------
FROM base AS development

ENV APP_ENV=local

COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

CMD ["php-fpm"]

# ---------------------------------------------------------------
# Dependencias Composer (secuencial tras base para no saturar RAM)
# ---------------------------------------------------------------
FROM base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------------------------------------------------------------
# Producción: código inmutable dentro de la imagen
# ---------------------------------------------------------------
FROM base AS production

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=wecar-ssr:latest /app/public/build ./public/build
COPY --from=wecar-ssr:latest /app/bootstrap/ssr ./bootstrap/ssr

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
