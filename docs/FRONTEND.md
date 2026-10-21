# Frontend and design system

The PteroHub frontend is a dark/violet responsive Blade interface. Admin and client/public areas share `public/assets/pterohub.css` and `public/assets/pterohub.js`; do not add extra application stylesheets or scripts. The official logo is `public/assets/pterohub-logo.webp`.

## RC 1.02 polish

- Keyboard skip link to the main content in admin and client layouts.
- Visible keyboard focus on interactive controls.
- Better minimum mobile tap targets and safer text overflow.
- Respect `prefers-reduced-motion`.
- Existing mobile navigation toggle and closing behavior retained.

## Frontend checklist

Check at approximately 360px, 390px, 768px, 1024px and desktop widths. Test the public home, game catalog, plan, cart, registration, login, account profile, services, orders, invoices, tickets and all admin sections. Check long usernames, long email addresses, error banners, empty tables and mobile navigation. Verify keyboard navigation (Tab, Shift+Tab, Escape), zoom at 200%, reduced motion and no horizontal page overflow.

When changing the CSS/JS, update the version query string in both layout files to prevent stale browser caches. Avoid decorative arrows in all UI elements.

## What is not claimed

This release has not been screenshot-tested on the user's live `alpha` VPS. Accessibility improvements are source-level changes; a full WCAG audit is still outstanding.
