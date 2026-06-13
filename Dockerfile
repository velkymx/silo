# syntax=docker/dockerfile:1

# ---------- Stage 1: build front-end assets ----------
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------- Stage 2: PHP runtime (nginx + php-fpm + workers) ----------
FROM php:8.4-fpm-bookworm AS app

# System deps + PHP extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor git unzip ca-certificates \
        libpng-dev libjpeg-dev libfreetype-dev libzip-dev libbz2-dev \
        libicu-dev libonig-dev libmagickwand-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd exif fileinfo zip bcmath intl bz2 pcntl pdo_mysql opcache \
    && pecl install imagick && docker-php-ext-enable imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# PHP dependencies (cached on composer files).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Application code + built assets.
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev

# Runtime config.
COPY docker/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/app/public storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf", "-n"]
