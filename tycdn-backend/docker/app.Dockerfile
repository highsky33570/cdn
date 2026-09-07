FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    icu-dev oniguruma-dev libzip-dev \
    mysql-client \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
    pdo_mysql mbstring gd intl zip bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- dependencies ---
FROM base AS deps
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# --- frontend build ---
FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npx vite build

# --- final image ---
FROM base

COPY . /var/www/html
COPY --from=deps /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend /build/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev \
  && chown -R www-data:www-data storage bootstrap/cache

# NOTE: config/route/view caching deliberately happens in the entrypoint at
# container start, NOT here. There is no .env in the build context, so caching
# during build would bake null into every env()-derived value (APP_KEY included).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
