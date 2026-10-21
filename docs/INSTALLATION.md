# Installation on Debian (new deployment)

This guide is for a **fresh installation**. For an existing instance use [DEPLOYMENT.md](DEPLOYMENT.md); never overwrite a working `.env` or drop the database. Commands assume a Debian 12/13 host, a non-root deployment user and a domain already pointed to the host. Package names and PHP-FPM socket paths vary by distribution.

## 1. Install dependencies

Install Nginx, MariaDB, PHP 8.2 or newer, Composer 2 and required PHP extensions. On Debian with a compatible PHP package repository, for example:

```bash
sudo apt update
sudo apt install nginx mariadb-server git unzip curl composer php-cli php-fpm php-mysql php-mbstring php-xml php-curl php-zip php-bcmath
php -v
composer --version
```

Ensure the CLI and PHP-FPM use compatible PHP versions. Enable TLS certificates (for example using Certbot) before enabling client registrations or SMTP.

## 2. Create the database

In a MariaDB administrative session:

```sql
CREATE DATABASE pterohub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pterohub'@'localhost' IDENTIFIED BY 'REPLACE_WITH_STRONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON pterohub.* TO 'pterohub'@'localhost';
FLUSH PRIVILEGES;
```

Use a unique password; do not copy the placeholder into production. If connecting via TCP `127.0.0.1`, ensure the database account host and connection configuration match.

## 3. Download and configure

```bash
sudo mkdir -p /var/www/pterohub-app
sudo chown "$USER":www-data /var/www/pterohub-app
git clone https://github.com/PteroHub/pterohub.git /var/www/pterohub-app
cd /var/www/pterohub-app
composer install --no-dev --optimize-autoloader
cp .env.example .env
chmod 640 .env
php artisan key:generate
```

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.example`, and the `DB_*` variables. Start with `PTEROHUB_SANDBOX_PAYMENTS=false` and `PH_CLIENT_REGISTRATION_ENABLED=false`. Set `MAIL_MAILER=log` until SMTP has been tested. Do not commit `.env`.

```bash
php artisan migrate --force
php artisan pterohub:create-admin
php artisan optimize:clear
php artisan view:cache
php artisan route:list
```

Ensure PHP-FPM can write `storage/` and `bootstrap/cache/` without making the entire source tree world-writable:

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Adjust ownership/group ACLs to match your deployment user and web server.

## 4. Configure Nginx

Use [nginx-pterohub.conf.example](nginx-pterohub.conf.example) as a starting point. Replace domain, TLS certificate paths and PHP-FPM socket with actual values. The document root **must** be `/var/www/pterohub-app/public`, never the repository root. Run `sudo nginx -t` before reload.

## 5. Post-install validation

```bash
php artisan migrate:status
php artisan pterohub:beta-audit
php artisan view:cache
```

Check `/up`, `/ph-admin/login`, `/client/login` and public catalog on desktop and mobile. Only then configure Pterodactyl and SMTP via the guides. Do not enable production payments: they are not implemented.
