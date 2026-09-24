# Customer account logs

`/console/account/login-logs` now matches the supplied login and operation log layouts:

- Login and operation tabs with a blue underline.
- Compact status/action, date range, and IP filters; operations also support category, object, and change content.
- Four login columns and seven operation columns, success/failure icons, horizontal scrolling, and left-aligned numbered pagination (10 per page initially).
- Per-tab filter retention, debounced text searches, inclusive end dates, loading/empty/error/retry states, and stale-response protection.
- Full operation change content opens in a read-only detail dialog.
- Responsive and dark theme support.

Login logs use the existing customer `/v1/log/login` API. Operation logs use `/v1/log/op` through the same customer-credential proxy, with GET as the only allowed method. No user-ID override or admin credential fallback is added.

Validation: build, TypeScript, ESLint, five API/allowlist tests (24 assertions), and local browser checks covering both tabs, filters, date ranges, pagination, detail content, failures, stale results, and mobile/dark layouts. Browser/API checks made no live changes.

## Installation

Extract this cumulative ZIP into `tycdn-backend`, replacing existing files, then run:

```sh
php artisan optimize:clear
```

Rebuilt production assets and previous console updates are included. No migration is required. Not deployed automatically.
