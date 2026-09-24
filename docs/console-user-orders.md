# Customer consumption records layout

`/console/billing/orders` now uses a dedicated customer orders page with the supplied reference layout:

- Compact type, status, and date-range filters.
- A horizontally scrollable table: ID, type, remarks, original amount, actual payment, more, payment method, order number, creation time, and paid indicator.
- Left-aligned numbered pagination, initially 10 records per page.
- Details opened from the More column; existing continue-payment and retry-processing actions remain available there.
- Loading, empty, failed-request/retry, mobile, and dark theme states.

Orders continue to come from the existing customer-scoped `/api/orders` endpoint. The supported local types are purchase, renewal, and recharge. Amounts display their recorded currencies; missing actual payments display a dash instead of an invented value. No master order records are merged or substituted.

The API now validates type and date filters. Date ranges include the entire ending day in the application's configured time zone and remain scoped to the signed-in customer.

Validation: production build, TypeScript, ESLint, two feature tests (12 assertions), and local browser checks for filters, pagination, currencies, payment/retry actions, errors, stale responses, empty/mobile/dark layouts. Browser mutations were mocked; no live orders were changed.

## Installation

This cumulative ZIP includes previous console updates and rebuilt production assets. Extract into `tycdn-backend`, replacing existing files, then run:

```sh
php artisan optimize:clear
```

No database migration is required. Not deployed automatically.
