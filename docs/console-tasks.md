# Tasks layout

Route: `/console/admin/workspace/tasks`

Dedicated tasks page matching the reference structure: bulk cancellation on the left, type/status/task ID/resource ID filters on the right, horizontally scrollable task table, and numbered pagination with first/last page links. Columns include ID, priority, name, type, resource, dependencies, start/end times, duration, state, failure count, and actions. Uses shared console controls and theme tokens, including dark mode and responsive layouts.

Reads the existing `/api/admin/workspace/tasks` endpoint. Normal listing sends `pid=0`; direct task ID searches omit the parent restriction. Task IDs support comma-separated numbers. Resource IDs remain strings. Filters reset pagination, text searches are debounced, and stale requests cannot replace newer results. Missing values and zero-second durations are handled separately. Disabled tasks display as cancelled even when the native API reports their state as done.

Details show a task's returned result when present, otherwise a paginated subtask dialog loaded with `pid=<task ID>`. The dialog supports refresh, individual results, formatted task data and cancellation. Results render as escaped text, including invalid JSON. Closing and reopening details for a different parent invalidates earlier requests. Dialog headers and footers stay accessible while the body scrolls.

Cancellation uses the existing PUT endpoint with exactly `{ "enable": 0 }`. Bulk and individual cancellation require confirmation, prevent repeated submission while running, refresh both task lists, and retain only failed IDs for retry. Already cancelled rows cannot be cancelled again. No backend endpoint changes.

Validation: Vue TypeScript, ESLint, Prettier, production Vite build; two existing backend authorization/cancellation tests (7 assertions); Chromium fixture checks (30 requests, 4 mocked cancellation attempts, no page errors). Browser checks cover columns, filter parameters, ID validation, pagination, result escaping, child data/results, child refresh and pagination, stale requests, cancellation and partial-failure retry, read failures, dark mode and mobile sizing. No production tasks were modified.

## Installation

The ZIP contains cumulative console source updates and current compiled assets. Extract into the Laravel backend directory, replacing matching files, then run:

```sh
php artisan optimize:clear
```

No migration or dependency installation is required. This session has not deployed the update.
