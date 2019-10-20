FROM php:5.6-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

RUN apt-get install -y gettext
RUN docker-php-ext-install gettext