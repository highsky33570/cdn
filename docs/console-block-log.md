# Admin block-log page

`/console/admin/workspace/history-blackip` and the existing `/console/admin/workspace/blackip` alias now open a dedicated block-log page matching the master's three-tab layout. The default tab is 当前拉黑; `?tab=stats` and `?tab=history` also open the corresponding tab directly.

- **当前拉黑:** selection, single/bulk IP unlock, website unlock dialog, blacklist export, IP/site/filter inputs, and the master's site ID, domain, IP, location, filter, block-time, expiry-time, and action columns.
- **拉黑统计:** refresh, rank/site/blacklist-count columns, and pagination over the complete ranking returned by the master.
- **历史拉黑:** IP/site/time filters, full filtered blacklist export, pagination, and manual-unlock status. There is no implicit one-hour cutoff. Time filters send Unix seconds, including the selected time of day.

The page supports light/dark themes and horizontal table scrolling on mobile. Page sizes match the master: 10, 30, 100, and 300. Filtering resets pagination, changing tabs clears stale selections, and older responses cannot overwrite a newer tab/query. “查看日志” opens access logs with the row's IP populated and its advanced filter visible.

## Verified API contract

Read-only checks against both live applications on 2026-09-22 returned one current block and 35 history records. The master's statistics endpoint returned one row, while the corresponding console endpoint returned 404. The new `blackip-count` workspace mapping fixes that missing endpoint.

Current records use `name` for the filter name and `fname` for its ID. History records use `fname` for the name and `filter` for the ID. Both use `create_at` as Unix seconds; current expiry is `exp`. History `auto_unlock=0` means manually unlocked (是), and `auto_unlock=1` means automatic (否). Site ID `0` is preserved.

Unlock requests are validated by the new admin-only controller and submitted to `/v1/jobs` as `unlock_ip` jobs with `key1=site_id`. A website unlock omits `ip`; single and bulk unlocks include it. A blank or invalid IP cannot silently become a website-wide unlock. The interface reports task submission, because the master completes these jobs asynchronously.

Exports proxy the master's `action=export` plain-text download through the authenticated backend, retaining filters and excluding pagination. Master credentials are never placed in browser download URLs. Live exports were confirmed to return newline-separated IP addresses.

## Deploy

`tycdn-block-log-console.zip` uses paths relative to the Laravel backend directory containing `artisan`.

1. Extract into that directory, merging the included `app/`, `routes/`, `resources/`, `tests/`, and `public/build/` files. Deploy the matching build manifest and assets together; retain old hashed assets while existing tabs may reference them.
2. Run `php artisan optimize:clear` so the new routes are available. Reload persistent PHP workers if used by the existing deployment.
3. Refresh the console and open `/console/admin/workspace/history-blackip`.

No SQL, migrations, dependency updates, or environment changes are required. This package contains no public frontend changes, secrets, or database files. Deployment has not been performed from this workspace.

## Validation

- Eight block-log PHP tests cover admin authorization, statistics, query forwarding, exact unlock-job payloads, invalid targets, full exports, upstream errors, and date validation. Fourteen related maintenance/usage regression tests also passed.
- All 17 JavaScript contract tests passed, including the new block-log field, timestamp, filter, and manual-unlock cases.
- Changed-file lint, PHP formatting, Vue/TypeScript checks, and the console production build passed.
- Local browser checks passed for all three tab schemas, real response-field rendering, site zero, filters/clear, pagination, single/bulk/site unlock payloads, filtered downloads, second-precision dates, errors/retry, access-log navigation, response ordering, empty state, dark theme, and mobile overflow.

Browser checks use captured response shapes plus synthetic edge cases. All four unlock requests were local test requests. No production blocks, jobs, or configuration were changed during verification.
