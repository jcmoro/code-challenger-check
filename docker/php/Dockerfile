# syntax=docker/dockerfile:1.7
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        autoconf \
        g++ \
        make \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del autoconf g++ make \
    && rm -rf /var/cache/apk/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_IDE_CONFIG="serverName=symfony"

WORKDIR /app

EXPOSE 9000
CMD ["php-fpm", "-F"]
