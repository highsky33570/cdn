# Admin usage layout

Route: `/console/admin/analytics/usage`

Replaces the generic admin usage table with bandwidth and traffic tabs, a combined search control, date presets, summary values, a responsive trend chart with tooltips, and a full detail table. Uses the console's shared tabs, buttons, selects, inputs, table, chart and theme tokens. The personal console and other analytics routes keep their existing views.

Bandwidth peak and 95th-percentile values come from the master's `max_value` and `max_95_value`; they are not recalculated from daily buckets. Traffic total, peak and average use the returned detail rows. Missing values display a dash. Chart, summary and table clear during loading or errors; failed reads can be retried and stale responses cannot replace the active query.

Reads the existing `/api/admin/workspace/usage` proxy. Supports package IDs, user ID, website domains, forwarding ports, business category, and custom dates. Multiple package IDs and resource values are whitespace-separated. Today covers today through tomorrow exclusive; yesterday and the last 7/30 days cover completed calendar days. A custom end date includes the selected day by sending the following date to the API. Date-only chart buckets use local midnight.

The shared `StreamMetricChart` has optional `heading` and `area` properties. Its existing callers retain the original rendering; area charts use project theme colors, a smooth line and a light fill without adding a chart dependency.

Validation: production Vite build, Vue TypeScript check, ESLint, and Chromium browser checks with mocked native responses. Browser coverage includes both views, unit conversion, server percentile, date boundaries, filter requests, validation, failed/empty reads, retry, stale response protection, dark mode and 390px mobile layouts. Tests make no production requests or configuration writes.

## Installation

The cumulative ZIP includes current source and compiled `public/build` assets, together with earlier console changes. Extract into the Laravel backend directory, replacing matching files. Then run:

```sh
php artisan optimize:clear
```

No database migration or dependency installation is required for this change. The update has not been deployed by this session.
