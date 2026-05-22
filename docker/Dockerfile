FROM php:7.3-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    default-mysql-client \
    nodejs		 \
    npm			 \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
    --with-freetype-dir=/usr \
    --with-jpeg-dir=/usr 

RUN docker-php-ext-install \
    pdo_mysql \ 
    intl \
    zip \
    curl \
    opcache \
    gd \
    mbstring \
    xml

COPY --from=composer:1 /usr/bin/composer /usr/bin/composer

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]

