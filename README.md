# Restaurant POS

Restaurant POS coursework application for Kedai Soto Banso. The application
uses Laravel, Livewire, and Microsoft SQL Server through the `sqlsrv` driver.

## Requirements

- Windows with Laravel Herd
- PHP 8.3 or newer
- Composer
- Node.js and npm
- Microsoft SQL Server, including the `sqlsrv` and `pdo_sqlsrv` PHP extensions
- SQL Server databases named `pos_restaurant_sabah` and
  `pos_restaurant_sabah_test`

Enable the PHP extensions used by the Herd PHP version:

```ini
extension=sqlsrv
extension=pdo_sqlsrv
```

Restart Herd after changing `php.ini`. Check the active CLI PHP with:

```powershell
php --ini
php -m | Select-String 'sqlsrv|pdo_sqlsrv'
```

## SQL Server setup

Create the application and test databases using SQL Server Management Studio or
`sqlcmd`:

```sql
IF DB_ID(N'pos_restaurant_sabah') IS NULL
    CREATE DATABASE pos_restaurant_sabah;
IF DB_ID(N'pos_restaurant_sabah_test') IS NULL
    CREATE DATABASE pos_restaurant_sabah_test;
```

The default configuration uses Windows authentication against the local
`SQLEXPRESS` named instance. Copy `.env.example` to `.env` and keep
`DB_USERNAME` and `DB_PASSWORD` empty for that setup.

For a SQL Server login instead, uncomment the SQL login block in `.env` and
set `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD`.

## Install and configure

From the project directory:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan storage:link
```

If the application database was previously used and should be recreated:

```powershell
php artisan migrate:fresh --seed
```

The seeded demo includes:

- User: `test@example.com`
- Password: `password`
- Business name, location, and phone
- Drink and Food categories
- Mee Sup Inanam and Set Lunch A items with modifiers
- Ten numbered tables plus `Table11` and `Walk in`
- Cash, Credit Card, and E-wallet payment methods

Open the Herd URL from `APP_URL` after starting or serving the project.

## Test

Tests use SQL Server only and target `pos_restaurant_sabah_test`. Confirm that
the test database exists and that the same `sqlsrv` and `pdo_sqlsrv` extensions
are enabled, then run the suite sequentially:

```powershell
php artisan test
```

Do not use SQLite, MySQL, PostgreSQL, or the `--parallel` option.

## Browser smoke check

After setup, log in with the seeded demo account and check:

1. Dashboard, responsive hamburger navigation, and Setup links.
2. POS table cards, open-order/create-order modals, and order line editing.
3. Payment method selection, reference validation, confirmation, and receipt.
4. Orders View and paid-order Print, including Ctrl+P thermal preview.
5. Table QR Print and Print All.
