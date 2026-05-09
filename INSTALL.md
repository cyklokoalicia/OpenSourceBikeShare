Open Source Bike Share Installation Manual
============

Prerequisites
----------
- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/)
- Git

Quick start (Docker)
----------

1. Clone the repository:
```bash
git clone https://github.com/cyklokoalicia/OpenSourceBikeShare.git
cd OpenSourceBikeShare
```

2. Copy the environment file and adjust the settings:
```bash
cp .env.dist .env.dev
```

3. Start the Docker containers:
```bash
docker compose up -d
```

To avoid port conflicts, you can override host ports via environment variables:
```bash
DB_PORT=3308 WEB_PORT=8200 docker compose up -d
```

4. Install PHP dependencies:
```bash
docker compose exec web composer install
```

The database schema is automatically created from `docker-data/mysql/create-database.sql` on first start.

The application will be available at:
- **Web app**: http://localhost
- **PHPMyAdmin**: http://localhost:81

Services overview
----------
| Service | Default port | Env variable | Description |
|---------|-------------|--------------|-------------|
| Nginx | 80 | `NGINX_PORT` | Reverse proxy |
| PHP 8.4 (Apache) | 8100 | `WEB_PORT` | Application server |
| MariaDB 10.3 | 3306 | `DB_PORT` | Database |
| PHPMyAdmin | 81 | `PMA_PORT` | Database admin UI |

Configuration (.env)
----------

Configuration is managed via environment variables in `.env.dev` (development) or `.env.prod` (production).

**Required settings:**
- `DB_HOST`, `DB_DATABASE`, `DB_USER`, `DB_PASSWORD` — database connection (pre-configured for Docker)
- `APP_NAME` — name of your bike sharing system
- `APP_SECRET` — unique secret for security (change for production)

**Optional — SMS system:**
- `SMS_CONNECTOR` — SMS gateway provider (`disabled`, `eurosms`, `textmagic`, `debug`). Leave `disabled` to turn off SMS.
- `SMS_CONNECTOR_CONFIG` — JSON configuration for the selected SMS connector.
- `COUNTRY_CODES` — JSON array of supported ISO 3166-1 alpha-2 country codes, e.g. `["SK", "CZ"]`.

**Optional — Credit (paid rental) system:**
- `CREDIT_SYSTEM_ENABLED` — `true` to enable charging for rentals, `false` for free rentals.
- `CREDIT_SYSTEM_CURRENCY` — currency symbol, e.g. `€` or `$`.
- `CREDIT_SYSTEM_MIN_BALANCE` — minimum credit required for bike operations.
- `CREDIT_SYSTEM_RENTAL_FEE` — fee charged after the free rental period.
- `CREDIT_SYSTEM_PRICE_CYCLE` — pricing model: `0` = one-time, `1` = flat recurring, `2` = doubling.
- `CREDIT_SYSTEM_LONG_RENTAL_FEE` — additional fee for long rentals.
- `CREDIT_SYSTEM_LIMIT_INCREASE_FEE` — fee to temporarily increase bike rental limit.
- `CREDIT_SYSTEM_VIOLATION_FEE` — fee for rule violations (applied by admins).
- `CREDIT_SYSTEM_LONG_STAND_DAYS` — minimum days a bike must stand before a return bonus applies (`0` = disabled).
- `CREDIT_SYSTEM_LONG_STAND_BONUS` — bonus credits for returning a long-standing bike.

**Optional — Watches and notifications:**
- `WATCHES_STACK` — `1` to notify admins when a non-top bike is rented from a stand.
- `WATCHES_LONG_RENTAL` — hours after which a rental is considered long (e.g. `24`).
- `NOTIFY_USER_ABOUT_LONG_RENTAL` — `true` to also notify the user about long rentals.
- `WATCHES_TIME_TOO_MANY` / `WATCHES_NUMBER_TOO_MANY` — thresholds for too many rentals in a short period.
- `WATCHES_FREE_TIME` — free rental time in minutes before charges begin.
- `WATCHES_FLAT_PRICE_CYCLE` / `WATCHES_DOUBLE_PRICE_CYCLE` / `WATCHES_DOUBLE_PRICE_CYCLE_CAP` — pricing cycle settings.

**Optional — SMTP (email notifications):**
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD` — mail server settings.
- `SMTP_FROM_EMAIL` — sender email address.

**Optional — Other:**
- `FORCE_STACK` — `true` to allow renting only the last returned bike at a stand.
- `CITIES` — JSON object of cities with coordinates, e.g. `{"Bratislava": [48.148, 17.117]}`.
- `SYSTEM_ZOOM` — default map zoom level.
- `SYSTEM_RULES` — URL to the system rules page.
- `USER_BIKE_LIMIT_AFTER_REGISTRATION` — number of bikes a new user can rent (`0` recommended for community systems).

Database setup
----------
The database schema is created automatically by `docker-data/mysql/create-database.sql` when the MariaDB container starts for the first time.

To populate test data:
```bash
docker compose exec -e APP_ENV=test web php bin/console load:fixtures
```

Admin user registration
----------
1. Open the application in your browser and register a new user at `/register`.
2. If SMS system is enabled, phone number verification is required during registration.
3. Set the first user's privileges to `7` (super admin) in the `users` database table.

Running tests
----------
```bash
docker compose exec -e APP_ENV=test web php bin/console cache:clear
docker compose exec -e APP_ENV=test web php bin/console load:fixtures
docker compose exec -e APP_ENV=test web vendor/bin/phpunit --configuration phpunit.xml
```

Or use the Composer shortcut:
```bash
docker compose exec web composer test
```

Need help to set it up?
---------
**We are also available to help you to set up your own bike sharing system** including the real world part (the stands, bicycles, locks etc.).

We will talk to you about the expectations, situation, bicycle theft, potential users and **provide you with help to launch your own successful bike sharing system**.

First consultation is free, **get in touch**: [consult@whitebikes.info](mailto:consult@whitebikes.info)