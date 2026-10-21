# PteroHub Installation Guide

This guide describes the deployment workflow for a self-hosted PteroHub installation.

**Important:** The commands below assume a Laravel-based PteroHub release. Verify the release's actual framework, runtime requirements, commands, and configuration variables before running them on a production server.

## 1. Prepare the Server

Recommended operating system: Debian 13 or another supported Linux distribution.

Install the required web server, PHP runtime and extensions, database server, Composer, Git, and Node.js if frontend compilation is required.

The exact versions must match the application's dependency manifests.

## 2. Create the Database

For a MariaDB installation:

```sql
CREATE DATABASE pterohub
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER 'pterohub'@'localhost'
IDENTIFIED BY 'REPLACE_WITH_STRONG_PASSWORD';

GRANT ALL PRIVILEGES ON pterohub.*
TO 'pterohub'@'localhost';
```

Use a unique, strong database password.

## 3. Download PteroHub

```bash
cd /var/www

git clone https://github.com/OWNER/PteroHub.git pterohub

cd /var/www/pterohub
```

Replace `OWNER` with the actual GitHub organization or username.

For production deployments, use a tested release tag rather than an arbitrary development commit.

## 4. Install Dependencies

For a Composer-based PHP application:

```bash
composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader
```

If the release contains a frontend build system:

```bash
npm ci
npm run build
```

Run these commands only when the corresponding manifest files and build scripts are included in the release.

## 5. Configure the Environment

```bash
cp .env.example .env
```

Edit the `.env` file and configure:

- Application URL and environment
- Database connection
- Mail transport
- Pterodactyl API connection
- Payment integrations
- Application-specific security settings

Use the exact variable names documented in the release's `.env.example`.

## 6. Initialize the Application

For a standard Laravel application:

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

Only run the commands supported by the actual application.

Do not run destructive database commands against an existing installation.

## 7. Configure Permissions

The web server must be able to write to the runtime storage and cache directories.

Example:

```bash
chown -R www-data:www-data \
  storage bootstrap/cache

chmod -R u+rwX,g+rwX \
  storage bootstrap/cache
```

Adjust the web server user to match your environment.

Never make the entire application directory world-writable.

## 8. Configure the Web Server

For a standard Laravel deployment, the document root must point to:

```text
/var/www/pterohub/public
```

Configure the web server to forward application requests to the PHP entry point and the correct PHP-FPM socket.

Enable HTTPS using a valid TLS certificate.

Do not expose `.env`, application source files, or private storage directories through the web server.

## 9. Configure Pterodactyl

Prepare the following:

- A working Pterodactyl Panel
- Its HTTPS URL
- An API key with the permissions required by PteroHub
- Available nodes and allocations
- Suitable nests and eggs for the products being sold

Configure the integration through the supported PteroHub settings.

Test connectivity before enabling automatic provisioning.

## 10. Configure Background Processes

If the installed release uses Laravel's scheduler, configure a cron entry equivalent to:

```cron
* * * * * cd /var/www/pterohub && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

If the application uses queued jobs, configure a process supervisor using the queue connection and worker command required by the release.

Do not assume that a scheduler or queue worker is required unless the application implements those features.

## 11. Configure HTTPS and Email

Before accepting real customers:

- Enable HTTPS
- Configure SMTP
- Verify outgoing messages
- Configure the public application URL
- Configure payment webhook endpoints, if supported
- Verify webhook signatures
- Confirm production credentials are not exposed

## 12. Initial Setup

Complete the application's supported administrator bootstrap process.

Then configure hosting products, server resources, pricing, customer-facing settings, and Pterodactyl integration.

The exact administrator creation command or setup wizard must be documented from the release implementation.

## 13. Verify the Installation

Confirm that:

- The public website loads correctly
- Customer registration and login work
- The administration area is accessible
- Database migrations are complete
- Email delivery works
- Pterodactyl connectivity is successful
- Server provisioning works in a test environment
- Payment flows work in sandbox mode
- Background jobs run correctly, if applicable

Do not enable live payments until the entire order-to-provisioning flow has been tested.

## 14. Production Security

Use least-privilege credentials, restrict administrative access, configure database backups, protect application secrets, and monitor application logs.

Always test upgrades in staging before applying them to production.
