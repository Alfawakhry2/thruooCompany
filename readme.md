# ThruooTest (Laravel)

Simple steps to run the project locally.

## Prerequisites
- PHP 8.1+ with extensions: openssl, pdo, mbstring, tokenizer, xml, ctype, json
- Composer
- MySQL/MariaDB
- Node.js (optional, only if you want to build frontend assets)

## Setup
1) Install PHP dependencies:
```
composer install
```

2) Create your env file:
```
copy .env.example .env
```

3) Update `.env` with your local DB credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=thruoocrm
DB_USERNAME=root
DB_PASSWORD=

TENANT_DOMAIN=thruoo.local
TENANT_DB_PREFIX= 
```

4) Generate app key:
```
php artisan key:generate
```

5) Run landlord migrations:
```
php artisan migrate --path=database/migrations/landlord
```

6) (Optional) Link storage:
```
php artisan storage:link
```

## Run
```
php artisan serve
```
App will be at `http://127.0.0.1:8000`.

