# Console access-log page

The admin route `/console/admin/analytics/logs` now uses a dedicated access-log page matching the master's `/dashboard/monitor/site/access-log` workflow.

## Page changes

- A single card with **日志查询** and **申请记录** tabs.
- Compact search-field selector, query button, applied-filter tags, clear action, advanced search, and download application.
- All 22 native data columns plus the detail action in a bordered, horizontally scrolling table. Millisecond timestamps, domain aliases, location, TLS fingerprints, origin/cache fields, node IDs, and numeric zero are preserved.
- Today's complete local calendar day is the default query range. Advanced search supports second-precision start/end times, domain, client IP, URI and matching mode, method, status, cache status, port, TLS fingerprint, node ID, referer, country, province, and ISP.
- Server pagination uses the master's count. Query page sizes: 10/30/100. Job page sizes: 10/30/100/300.
- Download applications use the applied filters, excluding pagination and unsaved search drafts. The records tab shows JobId / TaskId, application time, log time range, domain, state, raw progress, and download action.
- Details display request headers, response headers, and UTF-8 request bodies with a base64 toggle. Log content is rendered as text.
- Existing block-log links containing `?addr=...` still filter the access logs.
- Theme tokens support light/dark mode; narrow screens scroll the table within the card.

## API integration

| Console endpoint | Master endpoint |
| --- | --- |
| `GET /api/admin/workspace/access-log` (existing) | `GET /v1/monitor/site/access-log` |
| `GET /api/admin/access-log-jobs` | `GET /v1/jobs`, fixed type `down_http_access_log` |
| `POST /api/admin/access-log-jobs` | `POST /v1/jobs`, one object containing `type` and validated query `data` |
| `GET /api/admin/access-log-jobs/{id}/download` | `GET /v1/monitor/site/download-access-log/{id}` |
| `GET /api/admin/access-logs/{id}` | `GET /v1/monitor/site/access-log/{id}` |

All new routes use the existing authenticated admin middleware. Job submission also uses the existing admin write limit. Master credentials remain on the server. Gzip downloads stream through the console; unavailable files and upstream errors remain visible.

## Verification

On 2026-09-22, authenticated read-only requests confirmed the live master and console access-log response fields and count. The live master returned no download jobs; the console's new jobs endpoint was not yet deployed. A live row detail returned `req_header`, `resp_header`, and `req_body`. The public master access-log JavaScript matched the local panel asset byte for byte, confirming the job payload, columns, filters, and download/detail routes.

Checks passed:

- 9 targeted PHP tests, 41 assertions, including admin authorization, exact job payloads, validation, string document IDs, streaming, upstream errors, and existing user download regressions.
- 20 JavaScript contract tests.
- TypeScript checking, targeted ESLint, PHP Pint, and production Vite build.
- Local Chrome checks using captured live rows and controlled job/detail/download fixtures: tabs, columns, all filters, pagination, applied-versus-draft filters, job states/progress, gzip contents, UTF-8/base64 details, errors/retry, stale responses, IP deep links, light/dark, and mobile overflow.

No production jobs were created during verification. Download job creation and binary delivery were verified locally; no completed job existed on the master for a live download test.

## Deployment

The archive `.audit/console-validation/tycdn-access-log-console.zip` contains changed backend source files, tests, this document, and the complete compiled `public/build` directory. Paths are relative to `tycdn-backend`.

1. Extract into the deployed Laravel backend directory containing `artisan`, preserving other project files. Upload PHP routes/controller/service and compiled assets together. Retain existing hashed assets while old browser sessions may still reference them.
2. Run `php artisan optimize:clear` from that directory. Reload persistent PHP application workers if used.
3. Refresh `/console/admin/analytics/logs` and check both tabs. Applying a download creates a real master job; download availability depends on that job finishing.

No SQL, migrations, dependency installation, or server-side frontend build is required. This patch has been prepared and tested locally; it has not been deployed to production.
