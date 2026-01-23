FROM node:16-buster-slim AS node

FROM php:7.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    default-mysql-client \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
      --with-freetype-dir=/usr \
      --with-jpeg-dir=/usr \
  && docker-php-ext-install -j$(nproc) pdo_mysql intl zip gd

COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-app.ini

RUN a2enmod rewrite headers
COPY docker/app/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY --from=node /usr/local/ /usr/local/
RUN node -v && npm -v

RUN curl -fsSL https://getcomposer.org/download/1.10.26/composer.phar -o /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && composer --version

WORKDIR /var/www/html

COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
