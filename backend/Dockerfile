# ──────────────────────────────────────────────────────────────────
# Stage 1 : Composer dependencies
# ──────────────────────────────────────────────────────────────────
FROM composer:2.7 AS composer_stage

WORKDIR /app

# Copy only composer files first (layer cache)
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs

# Copy rest of the source and do full autoload
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ──────────────────────────────────────────────────────────────────
# Stage 2 : Final runtime image
# ──────────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    && mkdir -p /var/log/supervisor \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        zip \
        bcmath \
        opcache \
    && rm -rf /var/cache/apk/*

# Configure PHP OPcache for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.memory_consumption=192'; \
    echo 'opcache.max_wasted_percentage=10'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Nginx configuration
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy app from composer stage
COPY --from=composer_stage /app /var/www/html

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Fix permissions
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
