FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    libpng-dev libxml2-dev libpq-dev libonig-dev zip unzip git curl \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring xml gd bcmath ctype fileinfo \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080
