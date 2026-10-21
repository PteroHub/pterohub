# Architecture and lifecycle

PteroHub is a Laravel 12 application. Public/client/admin Blade views are under `resources/views/pterohub/`; HTTP controllers are in `app/Http/Controllers/`; business logic is in `app/Services/`; migrations are in `database/migrations/`; routes are split across `routes/pterohub-*.php` and included by `routes/web.php`.

## Components

- **Catalog and cart:** public game hosting plans, client draft orders, server-side price validation.
- **Accounts:** customer identity and verification; Pterodactyl Application API user sync; existing user IDs preserved.
- **Admin:** products, nodes, orders, services, invoices, support, notifications and diagnostics.
- **Provisioning:** explicit administrator action only, with attempt records and reconciliation to avoid uncertain duplicate creation.
- **Services:** remote identity snapshot, admin checks, local lifecycle events and manual renewal planning.
- **Email:** configurable SMTP, HTML notifications, delivery history and retry.
- **Sandbox payments:** provider-specific test-only attempts and webhook receipts separate from production commercial records.

## Trust boundaries

PteroHub database and Pterodactyl database are separate. Only Pterodactyl Application API calls are used for cross-system operations. The remote server's `online/offline` state is not inferred from its local `provisioned` status. A sandbox payment is evidence of a **test** transaction, not authorization to provision a game server.

## Files to preserve

- `public/assets/pterohub-logo.webp` official brand logo
- `public/assets/pterohub.css` the only application CSS
- `public/assets/pterohub.js` the only application JS
- Customer and admin routes, migrations and service links

No frontend compilation is required. Do not add parallel CSS/JS bundles or decorative arrow icons.
