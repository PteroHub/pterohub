# Troubleshooting

## HTTP 500 / blank page

Check `storage/logs/laravel.log` (the app may use a single log file rather than daily files), PHP-FPM logs, `APP_DEBUG=false`, directory permissions and PHP extensions. Do not share raw logs containing customer data or secrets publicly.

## HTTP 429 on SMTP settings

The older Beta 1.00 email test used overly strict shared throttles. The later rate-limit fix is included in this code. Check route throttling, proxy rules and actual response source before changing limits.

## Pterodactyl 403

Usually indicates insufficient Application API key permissions. Check only the relevant Users/Servers/Nodes permissions. Do not grant blanket access without need.

## Pterodactyl 422 on account sync

Read the sanitized field/code diagnostics from the account sync UI/logs. RC 1.02 inherits the validated JSON request behavior from Beta 1.12+. Do not retry uncertain account creations blindly.

## Sandbox migration error 1059

MySQL/MariaDB index names are limited to 64 characters. RC 1.01 fixed the sandbox index name and recovery after partial table creation. Do not drop existing payment tables.

## Email status Sent but no inbox delivery

`Sent` means the SMTP server accepted the message. Inspect the recipient, mail server delivery/bounce logs, spam folder and DNS authentication. An invalid `.test` recipient may be rejected.

## Assets not updating

Run `php artisan optimize:clear`, `php artisan view:cache`, then hard-refresh the browser. The two layouts include a version query string for CSS/JS.

## Admin/client redirects

The two areas use different route prefixes. Check the current login session and the appropriate `/client/login` or `/ph-admin/login` URL.
