# ==============================================================================
# Multi-Stage Dockerfile for Laravel Multi-Vendor Marketplace
# Stage 1: Frontend Asset Builder (Node.js 20 + Vite)
# Stage 2: Composer Production Dependencies (Composer 2)
# Stage 3: Base PHP 8.3 FPM Runtime with Extensions & Configurations
# Stage 4: Production Image (Self-contained, Optimized & Secure)
# Stage 5: Development Image (Live Bind-Mounts & Local Tooling)
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Frontend Asset Builder
# ------------------------------------------------------------------------------
FROM node:20-alpine AS frontend-builder
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci || npm install --no-audit

COPY resources/ ./resources/
COPY postcss.config.js* tailwind.config.js* vite.config.js* ./
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 2: Composer Dependency Builder
# ------------------------------------------------------------------------------
FROM composer:2 AS vendor-builder
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# ------------------------------------------------------------------------------
# Stage 3: Base Runtime Image
# ------------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS base

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=UTC

# Install system dependencies & PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    supervisor \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Copy PHP and OPcache configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy Composer binary for CLI runtime tasks
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install custom entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ------------------------------------------------------------------------------
# Stage 4: Production Image
# ------------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

# Copy production PHP vendor dependencies
COPY --from=vendor-builder /app/vendor /var/www/html/vendor

# Copy application code
COPY . /var/www/html

# Copy compiled frontend assets from Stage 1
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Setup storage & cache directory permissions
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["app"]

# ------------------------------------------------------------------------------
# Stage 5: Development Image
# ------------------------------------------------------------------------------
FROM base AS development

ENV APP_ENV=local \
    APP_DEBUG=true

# Development specific settings
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/99-custom.ini \
    && echo "display_startup_errors = On" >> /usr/local/etc/php/conf.d/99-custom.ini

ENTRYPOINT ["entrypoint.sh"]
CMD ["app"]