# syntax=docker/dockerfile:1

# Image de production de Convive. Un seul code, deux images :
#   - `app`  : PHP-FPM, utilisee aussi pour la file, le planificateur et les migrations ;
#   - `web`  : nginx, qui sert `public` (dont le site compile) et transmet le reste a PHP-FPM.
# La base de donnees n'est PAS dans l'image : elle vit sur le serveur, l'application s'y connecte par
# l'environnement (`DB_*`). Voir `docs/conteneurisation.md`.

ARG PHP_VERSION=8.3

# ---------------------------------------------------------------------------------------------------
# Base : PHP-FPM et ses extensions
# ---------------------------------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

# pdo_pgsql : la base sur le serveur. pdo_sqlite : l'etat actuel du code, tant qu'il n'est pas migre.
# gd, exif, zip, bcmath, intl : images (reencodage des fichiers deposes), exports et formats.
# pcntl : arret propre du processus de la file. postgresql-client : `pg_dump` pour les sauvegardes
# (sa version doit etre au moins celle du serveur PostgreSQL).
RUN install-php-extensions pdo_pgsql pdo_sqlite gd exif zip bcmath intl opcache pcntl \
    && apk add --no-cache postgresql-client tzdata

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-convive.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-convive.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------------------------------
# Construction : dependances PHP, puis site compile (Vite)
# ---------------------------------------------------------------------------------------------------
FROM base AS build

RUN apk add --no-cache nodejs npm git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Valeurs factices, pour cette etape seulement : le plugin Wayfinder de Vite lance `artisan` pour
# generer les routes du front. Rien de tout cela n'est repris dans l'image finale.
ENV APP_ENV=production \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    APP_URL=https://build.invalid \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory:

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
# .dockerignore exclut storage/framework : sans ces dossiers, Laravel refuse de demarrer
# ("Please provide a valid cache path") des `package:discover`.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules .git tests e2e qa docs test-results playwright-report

# ---------------------------------------------------------------------------------------------------
# Application (PHP-FPM, file, planificateur, migrations)
# ---------------------------------------------------------------------------------------------------
FROM base AS app

COPY --from=build --chown=www-data:www-data /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/convive-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/convive-entrypoint \
    && chmod +x /usr/local/bin/convive-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["convive-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------------------------------
# Serveur web (nginx) : fichiers statiques de `public`, le reste va a PHP-FPM
# ---------------------------------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /var/www/html/public
