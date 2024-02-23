FROM php:5.6-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# Because we using and old php version 5.6 some packages are not available and we should update source list or install directly
RUN sed -i '/security.debian.org/d' /etc/apt/sources.list \
    && sed -i '/deb.debian.org/d' /etc/apt/sources.list

RUN echo "deb http://archive.debian.org/debian/ stretch main" > /etc/apt/sources.list \
    && echo "deb http://archive.debian.org/debian-security stretch/updates main" >> /etc/apt/sources.list

RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++ wget git

RUN wget --no-check-certificate https://pecl.php.net/get/xdebug-2.5.5.tgz \
    && pecl install --offline ./xdebug-2.5.5.tgz \

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

RUN apt-get install -y gettext
RUN docker-php-ext-install gettext

COPY --from=composer:1 /usr/bin/composer /usr/local/bin/composer

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini