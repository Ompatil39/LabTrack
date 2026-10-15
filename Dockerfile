FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip curl \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-interaction --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html/logs /var/www/html/uploads

EXPOSE 80
CMD ["apache2-foreground"]
