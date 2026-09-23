# Admin CC rules workspace

`/console/admin/security/cc` now follows the supplied panel screenshots with the tabs 规则组, 匹配器 and 过滤器.

- Rule groups show selection, ID, name with source/sort subline, owner, type, display flag, synchronization/enabled status, creation time and management actions. The toolbar includes add, refresh and batch operations. Filters cover source, visibility, enablement, name, rule ID and owner ID. Summary pills use the native result total and current-page system/custom/display/disabled counts.
- Matchers show selection, ID, owner, name, source, status, creation time and actions. Filters include source/status/name/ID, an additional owner filter and active-filter count.
- Filters use the same layout with an additional filter-type column. Chinese labels cover request rate, browser verification, click/slide verification, simple click/slide verification, rotating images, CAPTCHA, five-second challenge, redirects and URL authentication.
- Pagination defaults to 10 rows with numbered pages and 10/30/100/300 page-size choices. Menus overlay the table without changing its height. Light, dark, empty, loading, failed-read and mobile layouts are supported.
- Management opens the existing structured editors. Filter type names are translated there too. Editing preserves matcher conditions and rule entries; empty groups such as 关闭 and an explicit sort value of zero can be saved.
- Row and batch enable/disable operations use the existing administrator CC API. Deletion asks for confirmation; system rule groups omit the row delete action, and mixed/system group selections cannot be batch-deleted from this UI. Failed bulk items remain selected; deletion failures keep the confirmation open for retry. Delayed list responses cannot replace the current tab/filter.

Native panel source and read-only API responses were checked: 11 rule groups, 3 matchers and 17 filters matched the reference. Mutation behavior was verified with local browser mocks; production security configuration was not changed.

## Validation

- Production build, Vue type checking, ESLint and formatting.
- Three JavaScript tests cover false/zero filters, synchronization statuses, filter labels and summary counts. The existing matcher round-trip contract test passes.
- The existing 14 backend v6 contract tests pass (54 assertions).
- Browser checks cover all three column layouts, real captured rows, pagination, zero-valued filters, delayed responses, empty-group editing, matcher data preservation, filter type preservation, creation failures/retry, partial bulk failures, deletion retry, empty/error states, menus and mobile/dark layouts.

## Applying the package

Extract the archive into `tycdn-backend`, preserving paths. Upload the included source files and complete `public/build` directory together, run `php artisan optimize:clear`, and hard-refresh the browser.

The archive includes earlier console updates. No SQL or database migration is required. It is not deployed automatically.
