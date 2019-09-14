LOCAL DEVELOPMENT
============
If you want you can run web for local development in docker.

How to start web over docker
----------
1. install docker and docker-compose
2. optional - if you want https. Generate keys to docker-data/nginx/ssl/bikesharing.loc.crt and bikesharing.loc.key. Uncomment lines in docker-data/nginx/nginx.conf and docker-compose.yml
3. add `127.0.0.1 bikesharing.loc` to `/etc/hosts`
3. run `docker-compose up`
4. go to `http://bikesharing.loc`, phpmyadmin is on url `http://localhost:81`, mysql server `db`, mysql user `root`, mysql password `password`, mysql database `bikesharing`
