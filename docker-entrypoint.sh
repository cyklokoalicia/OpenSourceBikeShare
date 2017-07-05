#!/bin/sh

cd /var/www/html

mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache

composer install

if [ -z "$(grep APP_KEY=base64 .env)" ]; then
	php artisan key:generate
fi


/usr/sbin/apache2ctl -D FOREGROUND
