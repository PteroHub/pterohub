# PteroHub RC 1.01 — Migration Recovery Fix

Full release based on RC 1.00. Fixes MySQL/MariaDB 64-character identifier limit for sandbox payment unique indexes and safely resumes migration after a partially created attempts table. No existing sandbox records are deleted. All RC 1.00 functionality is preserved.

Install after a backup:

    cd /var/www/pterohub-app
    unzip -o /root/pterohub-rc1.01.zip -d .
    php artisan migrate --force
    php artisan optimize:clear
    php artisan view:cache
    php artisan migrate:status

If migration reports a new error, stop and share it; do not drop any table. PHP/JS syntax and ZIP CRC were checked locally; database migration not executed on alpha.

---

# PteroHub RC 1.00 — Sandbox Payment Integration

Full source release built from user-tested Beta 1.20. **Release candidate for testing only, not production billing.**

## Included
- Stripe **test mode** hosted checkout for a client's eligible draft order. Uses `sk_test_` and `whsec_` credentials only. Stripe webhook HMAC signature and timestamp are verified; checkout session is re-fetched from Stripe before marking sandbox evidence `verified`.
- PayPal **sandbox** checkout. A returning customer must explicitly confirm capture with an authenticated, CSRF-protected POST. The order is re-fetched and must be approved; capture status, amount, currency, attempt reference and remote ID are checked. PayPal webhook signatures are verified via PayPal's API and event receipt logged, but webhook events do **not** independently settle PayPal payments in this RC.
- Read-only admin Sandbox payments screen, per-order sandbox attempt history, provider event receipts, exact minor-unit EUR amount checks, event deduplication, and mismatch review states.
- Sandbox evidence is kept **separate** from `payments`, `invoices`, `orders.status`, `orders.paid_at`, `hosting_services`, and all Pterodactyl provisioning. No real charges, automatic renewals, or server operations.
- Existing catalog, account sync, SMTP, Notification Center, manual provisioning, billing preview and renewal planning preserved.

## Safety limitations
- This release does NOT provide production Stripe/PayPal billing, recurring subscriptions, fiscal invoices, refunds, chargeback handling, automatic renewal, payment-triggered order confirmation or server provisioning.
- A successful browser return is NOT payment evidence. A Stripe test payment is verified by a signed webhook and provider API; PayPal is verified by an authenticated server-side capture.
- For uncertain payment initiation or capture, the attempt is marked `review`. Do not retry it blindly. Webhook processing errors are visible in logs and admin event records; failed event replay needs administrative reconciliation.
- Multiple concurrent payment initiations are not fully prevented by a database-level per-order uniqueness constraint. Test with a single browser session; resolve review states manually.
- Use only disposable sandbox accounts and **new test draft orders**, never the existing COD4 #1 production-linked service.

## Install (backup files and DB first)

    cd /var/www/pterohub-app
    unzip -o /root/pterohub-rc1.00.zip -d .
    php artisan migrate --force
    php artisan optimize:clear
    php artisan view:cache
    php artisan route:list --path=webhooks/pterohub
    php artisan route:list --path=client/sandbox

## Configuration (server-side .env only)

    PTEROHUB_SANDBOX_PAYMENTS=true
    PTEROHUB_STRIPE_TEST_SECRET=sk_test_...
    PTEROHUB_STRIPE_TEST_WEBHOOK_SECRET=whsec_...
    PTEROHUB_PAYPAL_SANDBOX_CLIENT_ID=...
    PTEROHUB_PAYPAL_SANDBOX_CLIENT_SECRET=...
    PTEROHUB_PAYPAL_SANDBOX_WEBHOOK_ID=...

Set up webhook endpoints with the sandbox/test accounts:

    https://dev.pterohub.com/webhooks/pterohub/stripe-sandbox
    https://dev.pterohub.com/webhooks/pterohub/paypal-sandbox

Stripe: subscribe to `checkout.session.completed`. PayPal: `PAYMENT.CAPTURE.COMPLETED` for verified event receipt. Set only the providers you want to test; absent credentials keep that provider disabled. Configure `APP_URL=https://dev.pterohub.com` so hosted checkout return URLs are correct. Never paste API secrets into ChatGPT or screenshots.

## Acceptance tests on alpha
1. Verify new migration and `/ph-admin/sandbox-payments` page.
2. With feature disabled, no checkout buttons; existing orders and COD4 #1 unchanged.
3. Enable Stripe test keys and webhook. Create a new **client draft** order, pay with Stripe test card, verify signed event and `verified` attempt.
4. Verify return URL alone does not mark payment verified. Wrong signatures return HTTP 400. Duplicate events do not create additional records.
5. Enable PayPal sandbox credentials, create a **different draft** order, approve using sandbox buyer, click Confirm sandbox capture. Verify amount and status.
6. Verify `orders.status` remains `draft`, `payments`/`invoices` unchanged, and no new server created.
7. Confirm account sync, SMTP test, mobile layout and existing manual provisioning read-only views still work.

## Validation
Local PHP syntax checks and JavaScript syntax check passed; archive CRC verified. No live provider credentials or `alpha` access were available, so **no real sandbox transactions were run**.
