FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts
COPY . ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

FROM php:8.3-apache
  
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev \
    && docker-php-ext-install mbstring opcache pdo_pgsql zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/ /var/www/html/
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/render-start.sh /usr/local/bin/render-start

RUN chmod +x /usr/local/bin/render-start

EXPOSE 10000
CMD ["/usr/local/bin/render-start"]
