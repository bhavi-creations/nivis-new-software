NIVIS DATABASE SETUP - LOCAL AND LIVE

Requirements: PHP 7.4+ with pdo_mysql, MySQL 8 or MariaDB 10.4+.

LOCAL XAMPP
1. Start Apache and MySQL.
2. Create nivis_labs_billing in phpMyAdmin if it does not exist.
3. Select that database, then import database/schema.sql.
   Alternatively: php database/setup.php
4. Open http://localhost/nivis-new-software/dashboard.php
5. Save your company details in Settings; configure applicable rates in Tax & GST.
6. Add categories, customers and products, then create a quotation, approve it,
   convert it to an invoice, issue it and record payments.

CONNECTION CONFIGURATION
Copy config/database.local.example.php to config/database.local.php.
Set host, port, database name, user and password for each server separately.
Local defaults are 127.0.0.1:3306, nivis_labs_billing, root, empty password.
NIVIS_DB_* environment variables override the local config file.
Never commit database.local.php or copy local root credentials to hosting.

LIVE / CPANEL
1. Create a database and database user in the hosting panel. Assign permissions.
2. Upload PHP pages, assets, config and database folders.
3. Set config/database.local.php to your hosting credentials including account
   prefixes. Use the database host supplied by the provider.
4. Select the hosting database in phpMyAdmin and import database/schema.sql.
   This file does not CREATE DATABASE or USE a hardcoded database name.
   With terminal access you can instead run php database/setup.php.
5. Open the domain/subfolder URL. API URLs are relative and work in either place.
6. Run php database/migrate_billing.php, then php database/create_admin.php.
   Sign in at login.php. Admin/manager accounts can write; staff accounts are read-only.
   Without users, only loopback requests are permitted for local XAMPP setup.
   Use HTTPS. Apache .htaccess denies direct config/database folder access;
   configure equivalent deny rules if your server uses nginx.

EXISTING DATABASE
Back up before schema changes. Schema import preserves table rows and refreshes
reporting views, but does not replace incompatible existing table definitions.
For older installations run:
  php database/migrate_product_defaults.php
  php database/migrate_identity_columns.php
The API also repairs supported missing optional columns/identity attributes.
ALTER privileges are needed only for these repairs.

OPTIONAL DEMO DATA
php database/setup.php --demo
Only use this for a demonstration database. Default setup imports schema only.
Individual SQL files are legacy exports; schema.sql is the portable combined import.

VALIDATION
node tests/integration.cjs
Creates and removes its own nivis_test_* database; never clears your application DB.
PHP_BINARY may override the PHP executable. Uses the same database credentials.
Tests CRUD, settings, schema repairs, quotations, GST calculations, invoice
conversion, payments, overpayment rejection and all seven report types.

Live connection cannot be verified without your hosting credentials/server access.
