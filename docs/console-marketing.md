# Marketing workspace

Updates `/console/admin/workspace/discounts`, `/console/admin/workspace/coupons`, and `/console/admin/workspace/coupon-historys` to the supplied marketing layouts. The workspace uses the project table, tabs, buttons, dialogs, inputs, selectors, switches, pagination, and theme colors.

- Discount configuration: compact summary/category/offer/validity/status columns, selection and deletion, and a dedicated editor with user groups, applicable packages, discount or fixed prices, optional dates, priority, and enable status.
- Coupon management: compact coupon/offer/usage/validity/status columns and a dedicated editor with code, price threshold, discount or fixed-price amount, renewal persistence, usage limit, categories, optional dates, and enable status. Existing package restrictions remain editable in a collapsed optional section.
- Usage history: debounced user ID and coupon-code filters, original and paid amounts, use time, and a read-only detail dialog.
- Each existing route opens its corresponding tab. Native request field names and numeric types are retained. Blank dates and usage limits are sent as null; empty group/package selections clear restrictions. Unchanged edits close without submitting a request that the native API would reject as unchanged.
- User-group options are loaded through an administrator-only, read-only proxy. Save failures retain the form; read failures offer retry; partial deletion failures retain failed IDs.

## Verification

- Production Vite build, Vue type checking, ESLint, Prettier, PHP formatting, and whitespace checks passed.
- Three backend tests / 16 assertions cover native fields and nullable limits, history filters, group lookup, read-only protections, and administrator access.
- Browser fixture checks passed: 41 requests, six simulated writes, no page errors. Coverage includes creation/editing, fixed-price discounts, group/package clearing, unlimited coupon usage, category validation, preservation of coupon restrictions, unchanged saves, deletion, filters, details, pagination, failed requests/retry, direct routes, empty states, dark theme inheritance, and mobile overflow.
- Desktop and mobile screenshots were visually reviewed. No production data was changed during testing.

## Deployment

Extract `tycdn-marketing.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The cumulative archive includes earlier console updates, current source and tests, and compiled assets with their manifest. No database migration is required. Deployment has not been performed automatically.
