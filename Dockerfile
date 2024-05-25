FROM php:7.4-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++ wget git zip

RUN pecl install xdebug-3.1.6
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

RUN apt-get install -y gettext
RUN docker-php-ext-install gettext

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini