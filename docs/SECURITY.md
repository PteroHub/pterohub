# Security and privacy

## Required controls

- Keep `APP_DEBUG=false` in public deployments. Serve only the `public/` directory.
- Restrict database privileges and Pterodactyl Application API permissions. Never expose Application API keys to browsers.
- Preserve Laravel CSRF protection on authenticated writes; webhook endpoints use provider signature verification rather than CSRF.
- Use HTTPS and verify email ownership before remote account creation.
- Enforce ownership checks on customer orders, services, invoices and support tickets.
- Avoid automatic claiming of Pterodactyl accounts by matching email alone.
- Use strong passwords and protect admin sessions.
- Keep secrets, database exports, logs, `.env` and backup files outside the repository and web root.

## Payment boundary

Stripe/PayPal integrations in RC 1.02 are sandbox-only and disabled by default. They do not authorize live payment collection, fiscal invoicing, service activation or automatic provisioning. Production billing requires a dedicated security review including signature verification, replay protection, idempotency, concurrency, refunds and accounting reconciliation.

## Operational checklist

Update dependencies, rotate leaked keys, review admin API access, monitor logs, back up and restore-test the database, and use staging for migrations. Do not log full request payloads or API tokens.
