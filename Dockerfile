FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    nginx \
    && docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

# PHP config
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/memory-limit.ini

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first (layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy app source
COPY . .

# Re-run composer scripts now that source is present
RUN composer run-script post-install-cmd --no-interaction 2>/dev/null || true

# Nginx config — same as dev but fastcgi_pass points to localhost
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN sed -i 's/fastcgi_pass php:9000/fastcgi_pass 127.0.0.1:9000/' /etc/nginx/conf.d/default.conf

# Minimal .env for Symfony bootstrap (real values come from env vars at runtime)
RUN printf 'APP_ENV=prod\nAPP_SECRET=changeme\nDATABASE_URL="mysql://localhost:3306/gotcha"\n' > .env

# Symfony prod cache warmup
ENV APP_ENV=prod
RUN bin/console cache:clear --env=prod --no-interaction \
    && bin/console cache:warmup --env=prod --no-interaction

# Fix permissions
RUN chown -R www-data:www-data var/

# Entrypoint: start PHP-FPM in background, Nginx in foreground
RUN printf '#!/bin/sh\nphp-fpm -D\nnginx -g "daemon off;"\n' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
