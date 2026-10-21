# Deployment, updates and rollback

## Before every update

1. Confirm the release notes and PHP/database compatibility.
2. Back up the database and the existing application, including the private `.env`, **outside** the web root and outside Git. Encrypt and restrict access to backups.
3. Test on a staging instance with a copy of non-sensitive data. Never test real payments on production.
4. Schedule maintenance and notify administrators if needed.

## Updating an existing Git deployment

```bash
cd /var/www/pterohub-app
php artisan down
git fetch origin
# Review the intended release tag or commit before checkout.
git checkout <reviewed-release-tag>
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan view:cache
php artisan up
```

**Do not run `git reset --hard` against an unreviewed live directory.** Do not replace or regenerate `APP_KEY`. Review schema changes before migrating. If a command fails, keep the application in maintenance mode and investigate.

## Updating an existing ZIP deployment

Back up first; unzip the reviewed full release into the app directory, preserve `.env` and runtime data, then run `php artisan migrate --force`, `php artisan optimize:clear`, and `php artisan view:cache`. Never delete database tables to resolve a migration failure.

## Rollback

Restore a consistent pair of **source + database** backups. Reverting code alone may not reverse a migration. If a migration fails, collect the error and inspect the schema before retrying. For RC 1.00, an overlong MySQL index name was fixed in RC 1.01; do not manually drop payment tables.

## Smoke tests

```bash
php artisan migrate:status
php artisan pterohub:beta-audit
php artisan route:list --path=ph-admin
php artisan route:list --path=client
php artisan view:cache
```

Then verify admin, client, public pages, customer ownership, account sync and mobile menus. Sandbox providers are tested separately and remain optional.
