# syntax=docker/dockerfile:1

# PHP 8.3 CLI base. We serve with `php artisan serve` (dev/demo web server), so
# there is no separate nginx/php-fpm — one process, easy to read.
FROM php:8.3-cli

# System libraries needed to build the PHP extensions Filament + Postgres require
# (the intl / zip / gd trio, plus libpq for pdo_pgsql).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libpq-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer, copied from the official Composer image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy the app, then install deps. APP_KEY is NOT baked here — it is injected from
# the environment (docker-compose.yml locally, the host env in production) so the
# image stays environment-agnostic and rebuilds don't churn the key.
COPY . .
RUN cp -n .env.example .env \
    && composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

# Default command — overridden per-service in docker-compose.yml.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
