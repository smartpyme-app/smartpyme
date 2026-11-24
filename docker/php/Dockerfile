# syntax=docker/dockerfile:1.7
# Backend SmartPyme (Laravel 12) — imita el servidor de producción:
# PHP 8.4 FPM con las mismas extensiones, OPcache 512 MB, zona horaria UTC
# y Redis por predis (en el servidor no está la extensión phpredis).
#
# Targets:
#   dev  -> el código se monta como volumen (docker-compose.yml)
#   prod -> imagen inmutable con el código y vendor dentro (para CI/CD)

ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2.7.34 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer

# Extensiones del servidor (php -m en producción). curl, dom, fileinfo, iconv,
# mbstring, pdo_sqlite, sodium, xml* y zlib ya vienen en la imagen oficial.
RUN install-php-extensions bcmath ftp gd mysqli pdo_mysql pcntl xsl zip opcache \
 && apt-get update \
 && apt-get install -y --no-install-recommends git unzip patch default-mysql-client tzdata \
 && rm -rf /var/lib/apt/lists/*

# Mismo usuario que en el servidor (smartpyme) para que los permisos de storage coincidan
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} smartpyme && useradd -u ${UID} -g smartpyme -m -s /bin/bash smartpyme

COPY --from=docker php/php.ini        /usr/local/etc/php/conf.d/zz-smartpyme.ini
COPY --from=docker php/www.conf       /usr/local/etc/php-fpm.d/www.conf

ENV TZ=UTC \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# ---------- desarrollo ----------
FROM base AS dev
# En local se revisan los archivos en cada petición (igual que el servidor hoy)
RUN echo "opcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/zz-opcache-dev.ini
USER smartpyme
CMD ["php-fpm"]

# ---------- dependencias de producción ----------
FROM base AS vendor
COPY composer.json composer.lock ./
COPY patches ./patches
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------- producción ----------
FROM base AS prod
COPY --chown=smartpyme:smartpyme . .
COPY --from=vendor --chown=smartpyme:smartpyme /var/www/html/vendor ./vendor
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R smartpyme:smartpyme storage bootstrap/cache
# En producción el código no cambia dentro del contenedor
RUN echo "opcache.validate_timestamps=0" > /usr/local/etc/php/conf.d/zz-opcache-prod.ini
USER smartpyme
CMD ["php-fpm"]
