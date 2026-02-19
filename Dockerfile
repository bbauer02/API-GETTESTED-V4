#syntax=docker/dockerfile:1

# Base FrankenPHP image with PHP 8.4
FROM dunglas/frankenphp:1-php8.4 AS base

# Install system dependencies and PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    apcu \
    uuid

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Common PHP configuration
RUN echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "realpath_cache_size=4096K" >> /usr/local/etc/php/conf.d/app.ini && \
    echo "realpath_cache_ttl=600" >> /usr/local/etc/php/conf.d/app.ini

# Expose ports
EXPOSE 80 443

# Development target
FROM base AS dev

RUN echo "display_errors=On" >> /usr/local/etc/php/conf.d/dev.ini && \
    echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/dev.ini && \
    echo "max_execution_time=120" >> /usr/local/etc/php/conf.d/dev.ini && \
    echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/dev.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/dev.ini

ENV APP_ENV=dev
ENV SERVER_NAME=":80, :443"

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# Production target
FROM base AS prod

RUN echo "display_errors=Off" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "max_execution_time=30" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "opcache.preload=/app/config/preload.php" >> /usr/local/etc/php/conf.d/prod.ini && \
    echo "opcache.preload_user=www-data" >> /usr/local/etc/php/conf.d/prod.ini

COPY . /app

RUN composer install --no-dev --optimize-autoloader --classmap-authoritative && \
    bin/console cache:warmup --env=prod

ENV APP_ENV=prod
ENV SERVER_NAME=":80, :443"

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
