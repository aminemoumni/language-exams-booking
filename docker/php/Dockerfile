# ─── Base stage ───────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    openssl \
    icu-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make

# PHP extensions
RUN docker-php-ext-install \
    zip \
    intl \
    mbstring

# MongoDB extension via PECL (tout en une seule couche)
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

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data var

EXPOSE 9000
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
