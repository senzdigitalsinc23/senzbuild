FROM php:8.4-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    intl \
    bcmath \
    gd \
    zip \
    opcache \
    xml \
    soap

# Install Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl clear-cache

# Install APCu
RUN pecl install apcu && docker-php-ext-enable apcu

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Production stage ---
FROM base AS production

# Copy custom PHP configs (production-tuned)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www

# Install dependencies only (no dev)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize

# Copy application code
COPY . .

# Create symlink so tools/run_migrations.php can find vendor/autoload.php
RUN ln -sf /var/www/vendor /var/www/tools/vendor \
    && ln -sf /var/www/Database /var/www/tools/Database

# Prepare storage directories
RUN mkdir -p storage/logs storage/files storage/jobs storage/backups \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Set entrypoint
RUN chmod +x docker/php/entrypoint.sh 2>/dev/null || true

EXPOSE 9000

ENTRYPOINT ["docker/php/entrypoint.sh"]
