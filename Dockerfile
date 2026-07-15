FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli zip gd bcmath intl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

WORKDIR /app/backend

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        storage/app/private \
        public/uploads/services \
        public/uploads/prestataires \
        /app/seed/uploads \
        /app/seed/private \
    && cp -a public/uploads/. /app/seed/uploads/ \
    && if [ -d storage/app/private ]; then cp -a storage/app/private/. /app/seed/private/; fi \
    && chmod -R ug+rwx storage bootstrap/cache public/uploads /app/seed

COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
