FROM php:8.4-fpm-alpine

LABEL maintainer="Zabbix Weathermap Team"
LABEL description="Zabbix Weathermap - Network visualization tool"

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    sqlite-dev \
    ttf-dejavu \
    fontconfig

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        zip \
        intl \
        mbstring \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create app directory structure
RUN mkdir -p /app/public \
    /app/app \
    /app/lib \
    /app/data/configs \
    /app/data/output \
    /app/data/cache \
    /app/logs \
    && chown -R www-data:www-data /app

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /app

# Copy application source
COPY src/ /app/

# Copy composer files and install dependencies
COPY docker/composer.docker.json /app/composer.json
COPY composer.lock* /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/public \
    && chmod -R 777 /app/data

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
