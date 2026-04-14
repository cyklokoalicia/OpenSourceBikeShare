LOCAL DEVELOPMENT
============
If you want you can run web for local development in docker.

How to start web over docker
----------
1. install docker
2. optional - if you want https. Generate keys to docker-data/nginx/ssl/bikesharing.loc.crt and bikesharing.loc.key. Uncomment lines in docker-data/nginx/nginx.conf and docker-compose.yml
3. run `docker compose up -d`
4. go to `http://localhost:8100`, phpmyadmin is on url `http://localhost:81`, mysql server `db`, mysql user `root`, mysql password `password`, mysql database `bikesharing`

To avoid port conflicts with other services, you can override host ports via environment variables:
```bash
DB_PORT=3308 WEB_PORT=8200 docker compose up -d
```

Available port variables: `NGINX_PORT` (default 80), `DB_PORT` (default 3306), `WEB_PORT` (default 8100), `PMA_PORT` (default 81).
