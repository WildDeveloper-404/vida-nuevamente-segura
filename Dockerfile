FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql zip \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-autoloader --no-scripts

COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i -e 's/\*:80>/*:10000>/' -e 's|DocumentRoot /var/www/html$|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && printf 'StartServers 1\nMinSpareServers 1\nMaxSpareServers 3\nMaxRequestWorkers 4\n' > /etc/apache2/mods-available/mpm_prefork.conf \
    && apache2ctl -t

EXPOSE 10000
