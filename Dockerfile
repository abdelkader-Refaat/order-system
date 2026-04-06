FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libsqlite3-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite pcntl zip sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize \
    && composer run-script post-autoload-dump --no-interaction || true

COPY docker/entrypoint.sh /usr/local/bin/order-system-entrypoint
RUN chmod +x /usr/local/bin/order-system-entrypoint

EXPOSE 8000

ENTRYPOINT ["order-system-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
