# PHP 8.3-FPM with the extensions Laravel + Filament + PostgreSQL need.
# bcmath is required for exact monetary math (spec #52 — no floats for money).
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev libzip-dev icu-dev oniguruma-dev \
        git unzip bash \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql bcmath intl zip opcache \
    && rm -rf /var/cache/apk/*

# Composer (pinned major) from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sensible opcache defaults for dev; override in production config.
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=0"; \
      echo "opcache.validate_timestamps=1"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
