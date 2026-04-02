FROM php:8.2-fpm-alpine

ARG SERVICE_DIR=gateway

RUN apk add --no-cache \
    bash curl git zip unzip libpng-dev libjpeg-turbo-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock* ./
COPY shared/ ./shared/
COPY ${SERVICE_DIR}/ ./${SERVICE_DIR}/

RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

EXPOSE 8000-8004

CMD ["php", "artisan", "serve", "--host=0.0.0.0"]
