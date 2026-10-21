# Configuration

Use `.env.example` as the list of supported environment settings. **Do not copy a production `.env` into a repository or issue.** Run `php artisan optimize:clear` after changing configuration, and re-cache as appropriate.

## Application and database

`APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` and `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` configure Laravel. In production use HTTPS, `APP_ENV=production`, `APP_DEBUG=false`. Keep the application key stable: changing it can invalidate encrypted SMTP settings and sessions.

## Pterodactyl

Set `PTERODACTYL_URL` to the panel origin (without `/api/application`) and `PTERODACTYL_API_KEY` to an **Application API** key. The panel is a separate Laravel application; it may run on the same VPS but does not share PteroHub's database. The key needs only the permissions required by features you enable. Account synchronization requires Users read/write; provisioning requires the relevant server/node/egg access. Avoid giving extra permissions.

Customer identity is synchronized after email verification. A pre-existing remote email is **not automatically claimed**; it needs administrative review. Existing links are maintained by remote user ID. Passwords are not synchronized. Manual server provisioning must remain an explicit administrative action.

## Client registration and verification

Set `PH_CLIENT_REGISTRATION_ENABLED=true` only after SMTP and HTTPS work. Optional Turnstile settings are `PH_TURNSTILE_SITE_KEY` and `PH_TURNSTILE_SECRET`. The exact verification and reset flows should be tested in staging.

## SMTP

PteroHub provides an administrative SMTP settings screen. For initial setup use `MAIL_MAILER=log` and safe `MAIL_FROM_ADDRESS`; then configure SMTP through the UI or Laravel mail variables. SMTP passwords entered through the UI are encrypted using `APP_KEY`. Test with an email address you control. The notification history status `Sent` means accepted by SMTP, **not necessarily delivered to the inbox**.

`PTEROHUB_TICKET_EMAILS=false` leaves ticket mail disabled by default. Commercial notification event toggles are configured in Admin > Notifications and are initially disabled.

## Stripe and PayPal sandbox only

`PTEROHUB_SANDBOX_PAYMENTS=false` is the safe default. Test keys are `PTEROHUB_STRIPE_TEST_SECRET`, `PTEROHUB_STRIPE_TEST_WEBHOOK_SECRET`, `PTEROHUB_PAYPAL_SANDBOX_CLIENT_ID`, `PTEROHUB_PAYPAL_SANDBOX_CLIENT_SECRET`, and `PTEROHUB_PAYPAL_SANDBOX_WEBHOOK_ID`. Use only sandbox credentials. The webhook endpoints are `/webhooks/pterohub/stripe-sandbox` and `/webhooks/pterohub/paypal-sandbox`. Never put webhook secrets in URLs or frontend JavaScript.

**Sandbox settlement does not update live orders or activate services.** Do not attempt to use production keys. This RC has not been certified for production payment processing.
