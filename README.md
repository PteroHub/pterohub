# PteroHub

PteroHub is a Laravel 12 game-server hosting management and billing platform integrated with the Pterodactyl Application API. It provides a public hosting catalog, client portal, administrative management, manual server provisioning, customer account synchronization, SMTP notifications, service diagnostics and simulated billing.

> **Release candidate RC 1.02, not production-ready billing software.** Stripe and PayPal integrations are **sandbox-only**, disabled by default. Real charges, fiscal invoices, automatic server provisioning, automatic renewals, refunds and production payment settlement are **not implemented or approved for production**.

## Requirements

- PHP 8.2+ and common Laravel extensions (`mbstring`, `xml`, `curl`, `zip`, `pdo_mysql`, `openssl`, `ctype`, `tokenizer`, `fileinfo`, `bcmath`)
- Composer 2
- MySQL 8 / MariaDB (tested deployment uses MariaDB; migration compatibility should be checked on your version)
- Nginx or Apache with document root pointing to `public/`
- A separately installed Pterodactyl panel with an Application API key (optional for initial UI setup)
- HTTPS and working SMTP for account verification

No Node.js build is required: the frontend uses **one CSS** file (`public/assets/pterohub.css`) and **one JavaScript** file (`public/assets/pterohub.js`).

## Quick start (new installation)

```bash
git clone https://github.com/PteroHub/pterohub.git
cd pterohub
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# Edit .env with your database, APP_URL and mail configuration.
php artisan migrate --force
php artisan pterohub:create-admin
php artisan optimize:clear
php artisan view:cache
```

Configure your web server to serve `public/`, then visit `/ph-admin/login`. For a local development preview use `php artisan serve` and `APP_URL=http://127.0.0.1:8000`. **Do not use `artisan serve` as a public production web server.**

**Important:** This repository contains application source and the Laravel entrypoint, but intentionally excludes `vendor/`, `.env`, runtime files, uploaded data, backups and credentials. Do not copy your production `.env` into Git.

## Documentation

| Guide | Contents |
| --- | --- |
| [Installation](docs/INSTALLATION.md) | Debian, PHP, MariaDB, Nginx, permissions, admin account |
| [Configuration](docs/CONFIGURATION.md) | Environment, SMTP, Pterodactyl API, sandbox providers |
| [Deployment and upgrades](docs/DEPLOYMENT.md) | Safe updates, backups, rollback and validation |
| [Architecture](docs/ARCHITECTURE.md) | Application structure, integration boundaries and lifecycle |
| [Frontend](docs/FRONTEND.md) | Single CSS/JS policy, accessibility and mobile testing |
| [Security](docs/SECURITY.md) | API permissions, data handling and disclosure |
| [Troubleshooting](docs/TROUBLESHOOTING.md) | Common setup and integration problems |
| [Release readiness](docs/RELEASE_CHECKLIST.md) | Remaining work before production |
| [GitHub publishing](docs/GITHUB.md) | Safe repository upload, secrets and tagging |

## URLs

- `/` public website
- `/client` customer portal
- `/ph-admin/login` administrator login
- `/ph-admin` administration
- `/ph-admin/sandbox-payments` sandbox payment diagnostics

## Current safety boundaries

- Game server provisioning is **manual** and requires an administrator action.
- Client identity synchronization uses the Pterodactyl Application API. Existing linked user IDs and server ownership are preserved.
- Customer passwords are not synchronized with Pterodactyl.
- Service renewal planning is **not** automatic renewal or payment collection.
- Sandbox payment evidence is separate from commercial orders, invoices and services.
- Production payments must remain disabled until a separate audited release.

## Development and validation

```bash
composer install
php artisan route:list
php artisan migrate:status
php artisan pterohub:beta-audit  # read-only database consistency checks
php artisan view:cache
```

`pterohub:beta-audit` is read-only but requires a configured database and completed migrations. The release ZIP is not a backup of your database or live configuration.

## Contributing and license

See [CONTRIBUTING.md](CONTRIBUTING.md). **License not yet selected by the project owner.** The repository is currently marked `proprietary` in Composer to avoid implying an open-source license. Public source visibility does not automatically grant reuse rights. Choose and add a license before inviting external redistribution.
