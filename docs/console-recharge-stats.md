# Recharge statistics layout

Route: `/console/admin/finance/recharge-count`

Uses a dedicated statistics page with the reference heading and grouped-record count on the left; user ID, day/month/year grouping and date-range controls on the right; a two-column 时间 / 金额 table; and numbered pagination. Shared console controls and theme tokens support dark mode and mobile layouts. The two-column table fits mobile screens without horizontal scrolling.

Reads the existing `/api/admin/workspace/recharge-count` endpoint with `type=充值` and `state=已付款`. Amounts returned by this endpoint are already in yuan and display with two decimal places plus 元; they are not divided by 100. Missing amounts display a dash. Pagination and both record counts use the server's grouped-record count.

Default dates cover one calendar month ago through today, with the previous-month date clamped at month end. Dates selected in the UI are inclusive; requests use the following day as the native API's exclusive end. Clearing dates removes both date parameters. User ID search is debounced and also supports Enter. Filter changes reset the page, and stale requests cannot replace current results. Loading and failed reads clear stale table data; errors support retry.

Validation: Vue TypeScript check, ESLint, production Vite build, and Chromium tests against mocked responses. Browser checks cover both columns, yuan formatting, missing/zero values, grouped counts, server pagination, all grouping modes, user/date filters, validation, overlapping responses, empty/error states, retry, theme colors and mobile layout/calendar. No backend changes or production data writes.

## Installation

The ZIP contains cumulative console source updates and current compiled assets. Extract into the Laravel backend directory, replacing matching files, then run:

```sh
php artisan optimize:clear
```

No migration or dependency installation is required. This session has not deployed the update.
