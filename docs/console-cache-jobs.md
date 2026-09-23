# Admin cache refresh and preload

`/console/admin/cache/jobs` now follows the supplied panel cache-clean screenshots. The administrator route uses a dedicated page with two tabs; the personal console retains its existing page.

- **刷新预热**: operation selector, current-operation badge, large URL input, live nonblank-line count, input guidance, clear/submit controls, daily quota with usage bar, and submission status in a right-hand column.
- **操作记录**: selected-task resubmission, operation filter, URL/domain search, page-local status counts, the reference columns (selection, task identifiers, type, URL, status, creation time, actions), and right-aligned pagination starting at 10 rows.
- URL refresh, directory refresh and preload submit the native array of `{type, data: {url}}` jobs. Invalid HTTP URLs, known quota overflow and batches above the existing 1,000-job API limit are blocked. Blank lines are ignored. Failed submissions retain the input; failed resubmissions retain the selection.
- Quotas are read from the matching global site configuration and today's native job count. Delayed responses from an older operation cannot overwrite the selected operation. Missing or failed quota data is shown as unknown, with retry, rather than displaying an invented limit.
- History searches send domains in `key1` and full URLs in `key2` with no double encoding. All-types queries include only the three supported cache job types. Delayed history queries cannot overwrite a newer filter.
- Rows show both Job and Task identifiers, copyable URLs, cancelled/failed/pending/processing/completed status, resubmission, failure details and refreshable progress. Details dialogs are centered; failure text is rendered as text. Unknown status values are not labelled completed.
- Light, dark, empty, error and narrow-screen layouts are supported.

Read-only native API checks confirmed quotas of 2,000 URL refreshes, 500 directory refreshes and 2,000 preloads, all unused at verification time. Those values are loaded from the API, not hardcoded. The production history was empty. Browser tests use local records to verify populated history and job submission; no production jobs were created.

## Validation

- Production build, Vue type checking, ESLint and formatting.
- Eight backend tests (147 assertions) for administrator routes, cache-job payloads and quota contracts, including invalid operation/date rejection and unknown quota values.
- Four JavaScript tests for URL input, safe native resubmission payloads, URL/domain query encoding and status summaries.
- Browser checks for both reference layouts, mode-specific quotas, stale responses, invalid/over-quota input, failed submission/retry, native payloads, filtering, batch retry, malformed history records, details/progress, pagination and mobile/dark layouts.

## Applying the package

Extract the archive into `tycdn-backend`, preserving paths. Upload the included source files and complete `public/build` directory together, then run `php artisan optimize:clear` and hard-refresh the browser. The updated workspace controller and API routes are required for the quota cards.

The archive includes earlier console updates. No SQL or database migration is required. It is not deployed automatically.
