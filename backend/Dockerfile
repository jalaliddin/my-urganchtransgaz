# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# vendor: install PHP dependencies against the real app code (composer
# scripts like `package:discover` need artisan present), then dump a
# production-optimized autoloader. Shared by the fpm/nginx runtime stages
# so the backend (php-fpm) and backend-nginx (static+fastcgi front) images
# are always built from the exact same code + vendor tree.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && composer run-script post-autoload-dump --no-interaction

# ---------------------------------------------------------------------------
# vendor-dev: same, but with dev dependencies included. Exists only because
# `OrganizationSeeder`/`DepartmentSeeder`/`PositionSeeder` — which encode
# this company's *real* org structure, not test fixtures — go through
# Eloquent factories, and a factory always calls the `fake()` helper
# (fakerphp/faker, require-dev) even for fields the seeder overrides with
# real values. Used only by the one-off `artisan` tooling target below, so
# the always-running backend/scheduler images stay on the lean, dev-free
# `vendor` stage.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor-dev
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize \
    && composer run-script post-autoload-dump --no-interaction

# ---------------------------------------------------------------------------
# php-base: system packages + PHP extensions shared by every PHP runtime
# stage below (no app code yet, so this layer is cached across them all).
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS php-base
RUN apk add --no-cache libpng libzip libjpeg-turbo freetype icu-libs oniguruma libxml2 \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        libpng-dev libzip-dev libjpeg-turbo-dev freetype-dev icu-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath exif intl opcache \
    && apk del .build-deps
WORKDIR /var/www/html
COPY docker/php/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ---------------------------------------------------------------------------
# fpm: the `backend`/`scheduler` services — runs the actual application via
# PHP-FPM on port 9000. Nothing here is reachable directly; the
# backend-nginx service (below) or your own reverse proxy talks to it over
# FastCGI, and `scheduler` overrides CMD to run `schedule:work` instead.
# ---------------------------------------------------------------------------
FROM php-base AS fpm
COPY --from=vendor /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# fpm-tools: identical to `fpm` but built from `vendor-dev` — the `artisan`
# one-off service (migrations, seeding, tinker) runs from this target so
# `db:seed` actually works, without shipping dev tooling in the long-running
# backend/scheduler containers.
# ---------------------------------------------------------------------------
FROM php-base AS fpm-tools
COPY --from=vendor-dev /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan"]

# ---------------------------------------------------------------------------
# nginx: the `backend-nginx` service — serves public/'s static files
# directly and fastcgi_pass'es *.php requests to backend:9000. Built from
# the same `vendor` stage so its copy of public/ (and thus its notion of
# where index.php "is") always matches what the fpm container has on disk.
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx
COPY --from=vendor /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
