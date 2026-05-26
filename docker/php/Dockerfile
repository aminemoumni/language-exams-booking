# ─── Base stage ───────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    openssl \
    openssl-dev \
    icu-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    pkgconf

# PHP extensions
RUN docker-php-ext-install \
    zip \
    intl \
    mbstring

# MongoDB extension via PECL
# openssl-dev must be present BEFORE this step so libmongoc is compiled
# with ENABLE_SSL → SCRAM_SHA_256 support
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─── Dev stage ────────────────────────────────────────────────────────────────
FROM base AS dev

COPY composer.json composer.lock* ./

RUN composer install --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize

# Entrypoint: recreates var/ directories and fixes permissions on every start.
# This is necessary because the docker-compose bind mount (./backend:/var/www/html)
# overlays the var/ directory built into the image, so permissions must be
# re-applied at runtime rather than only at build time.
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# ─── Prod stage ───────────────────────────────────────────────────────────────
FROM base AS prod

ENV APP_ENV=prod

COPY composer.json composer.lock* ./

RUN composer install --prefer-dist --no-dev --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && chown -R www-data:www-data var \
    && chmod -R 755 var

EXPOSE 9000
CMD ["php-fpm"]
