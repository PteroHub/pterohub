# Release readiness checklist

RC 1.02 is **not** a final production release. The following must be completed before accepting real customer payments:

- [ ] Full end-to-end fresh installation from the public Git repository
- [ ] Full regression on mobile, tablet and desktop; accessibility audit
- [ ] Security review of customer authorization, session protection and remote API access
- [ ] New-customer email verification and remote Pterodactyl user creation on staging
- [ ] Customer profile changes, email confirmation, remote username sync and conflict handling
- [ ] Stripe test checkout, signed webhook, duplicate/replay tests and reconciliation
- [ ] PayPal sandbox approval/capture, verified webhook and uncertain-state recovery
- [ ] Real payment settlement model and accounting reconciliation (not present)
- [ ] Fiscal invoice compliance for target jurisdictions (not present)
- [ ] Refund, dispute, chargeback and cancellation flows (not present)
- [ ] Automated renewal policy, customer consent and failed-payment handling (not present)
- [ ] Background job/queue reliability, retry limits and operational alerts
- [ ] Backup restoration and rollback rehearsal
- [ ] Production deployment hardening and privacy/legal documentation

Current validated functionality reported by the project owner includes manual COD4 provisioning, customer profile sync, SMTP test delivery, notification history, renewal planning and the Beta 1.20 read-only integrity audit. This does not establish production readiness.
