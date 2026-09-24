# User cache refresh and preheat layout

Updates `/console/cache/jobs` (and its `/console/cache` alias) to the supplied screenshots.

- Flat white workspace with underlined 刷新预热 / 操作记录 tabs.
- Radio operation selection, 625 × 275 px URL field, live quota text and aligned submit button.
- Compact records toolbar, type and URL/domain filters, selectable table, status details, per-row and bulk resubmission, numbered pagination starting at 10 rows.
- User jobs use the existing authenticated `/api/cdn/proxy/v1/jobs` API. URL validation and native job status/payload helpers are preserved. Failed submissions retain their inputs or selection.
- New read-only `/api/cdn/cache-quota` retrieves only the three native cache quota settings and today's job count with the signed-in customer's credentials. No master credential fallback. Missing values remain unknown; failures expose a retry control. An upstream permission restriction can make quota unavailable without preventing task submission.
- Responsive form, horizontally scrolling table, keyboard tabs and dark theme support.

Validation: production build, Vue/TypeScript check, ESLint, PHP style check, six quota feature tests (76 assertions), and local browser checks against compiled assets. Browser checks cover validation, all operation types, quota failures and stale requests, submission/resubmission errors, filtering, pagination, status details and responsive layouts. No production jobs were submitted.

## Apply

This is a cumulative update, including the previous certificate, sites, analytics, logs, sidebar and admin changes. Extract the ZIP into `tycdn-backend`, preserving the included paths and replacing matching files. Run:

```sh
php artisan optimize:clear
```

Compiled frontend assets are included. No database migration is required. This package has not been deployed by the assistant.
