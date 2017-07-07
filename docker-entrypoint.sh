#!/bin/sh

cd /var/www/html

mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache

chown -R o+w . 

composer install

if [ ! -f ".env" ]; then
	cp .env.example .env
fi
if [ -z "$(grep APP_KEY=base64 .env)" ]; then

	php artisan key:generate
	php artisan migrate
fi


/usr/sbin/apache2ctl -D FOREGROUND
