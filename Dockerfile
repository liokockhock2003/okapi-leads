# syntax=docker/dockerfile:1

# base — local development (php artisan serve). docker-compose builds this stage.
FROM php:8.3-cli AS base

# PHP extensions for Filament + Postgres (intl / zip / gd + pdo_pgsql).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libpq-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .
RUN cp -n .env.example .env \
    && composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# production — adds Laravel Octane (Swoole) to run as one persistent process.
# Build with: docker build --target production -t <image> .
FROM base AS production

RUN pecl install swoole \
    && docker-php-ext-enable swoole \
    && composer require laravel/octane --no-interaction \
    && php artisan octane:install --server=swoole --no-interaction

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
