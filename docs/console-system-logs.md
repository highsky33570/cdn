# System logs layout

Route: `/console/admin/monitoring`

The page now opens on 登录日志 and follows the reference's four-tab layout: 登录日志, 操作日志, 备份日志 and 发信日志. Shared console controls, tables, badges and theme tokens provide consistent colors and backgrounds in light/dark mode. Wide tables and change-detail tables scroll horizontally on small screens without widening the page.

Login logs have status/date/user/IP filters and columns for user ID, IP, location, browser UA, date/time and status. Missing browser UA is displayed as 未记录 because the current master contract does not always supply it. Unknown login states are distinguished from failures.

Operation logs have action/date/user/IP/category/object/change filters, grouped category/object and IP/location columns, compact change summaries, and a detail dialog comparing old/new values. Arrays, individual change objects and keyed collections are supported; unstructured or invalid JSON remains available as escaped text. Zero and false values are preserved.

Backup logs show record counts, refresh, backup/completion times, state and result. They read `/v1/jobs` with a fixed `type=backup` filter, matching the native panel. Pending/running jobs with no completion time display 未完成. Sending logs show type/media/state/user/message filters, recipient, message ID, title/content, medium, failure count, state, reason and sending time. Full backup/sending results open in a scrollable dialog. No message sending or backup creation is performed.

All tabs use server pagination with first/last page links, retain their own filters when switching tabs, and reset to page one after filter changes. Text searches are debounced; stale responses cannot overwrite the current tab. Selected date ranges are inclusive in the UI and use the following day as the native API's exclusive end. Empty/error states, retries and unknown values are handled explicitly.

## API and validation

Existing `/api/admin/logs/login` and `/api/admin/logs/op` reads are retained. New GET-only endpoints `/api/admin/logs/backup` and `/api/admin/logs/msg-send` use the existing administrator/verification middleware and master API service. Backup requests allowlist pagination and force the backup type; sending logs validate and allowlist their query filters. No database changes.

Validation passed: production Vite build, Vue TypeScript, ESLint, Prettier, PHP Pint, three backend tests with 18 assertions, and Chromium fixture checks (28 reads, zero writes, no page errors). Browser checks covered all tab columns, filters and date bounds, pagination, detail formats and HTML escaping, empty/error/retry behavior, stale requests, dark mode and mobile/calendar sizing.

## Installation

The ZIP contains cumulative console updates and current compiled assets. Extract into the Laravel backend directory, replacing matching files, then run:

```sh
php artisan optimize:clear
```

No migration or dependency installation is required. Not deployed by this session.
