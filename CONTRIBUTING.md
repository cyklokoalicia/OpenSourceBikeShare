LOCAL DEVELOPMENT
============
If you want you can run web for local development in docker.

How to start web over docker
----------
1. install docker
2. optional - if you want https. Generate keys to docker-data/nginx/ssl/bikesharing.loc.crt and bikesharing.loc.key. Uncomment lines in docker-data/nginx/nginx.conf and docker-compose.yml
3. run `docker compose up -d`
4. go to `http://localhost:8100`, phpmyadmin is on url `http://localhost:81`, mysql server `db`, mysql user `root`, mysql password `password`, mysql database `bikesharing`
