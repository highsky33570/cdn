# Admin website workspace

`/console/admin/sites` now follows the supplied panel website-list screenshots, with five tabs: 网站列表, 分组管理, 默认设置, DNS API and 解析检测.

- The website table includes selection, ID, domain with owner, CNAME with copy, HTTPS status, origin/listeners, package/group, region, status, creation time and actions. The compact toolbar provides add, bulk edit, certificate requests, enable/disable/delete, search, filters and CSV export. Management and domain links open the existing site configuration route; the row menu retains the read-only details view.
- Group management includes owner/name/remark columns, add/edit, refresh, selection and deletion.
- Defaults use the inset settings panel, owner/setting/value/scope columns, typed settings inputs, group/global scope and enable/disable actions.
- DNS API includes owner/name/type/remark columns and provider credential forms. Existing credentials are not returned in list/detail responses; editing metadata leaves credentials unchanged unless replacement is explicitly selected.
- Resolution checks include native DNS API/task filters, domain/site search, page-local summaries, status badges, copy actions and selected-domain synchronization. Automatic CNAME checks are read-only; synchronization only runs on an explicit button click. Delayed checks cannot overwrite a newer tab or query.
- Pagination defaults to 10 rows. Empty, loading, error, retry, light/dark and narrow-screen layouts are supported. Failed bulk items remain selected for retry.

Native panel reads verified the site fields, all four related resource lists, `dnsapi_state=set/not_set` filtering and prefixed-key CNAME checks using administrator API authentication. Group/default/DNS writes and synchronization were checked with local mock responses; production records were not changed.

## Validation

- Production build, Vue type checking, ESLint and formatting.
- Five backend tests (25 assertions) covering administrator access, resource/method restrictions, owner and scope payloads, credential omission, DNS-check objects and selected synchronization IDs.
- Four JavaScript tests covering CNAME modes, JSON-encoded origin/listener fields, nonhealthy site states and CSV escaping.
- Browser checks cover all five table layouts, real captured site rows, search/filtering, export, bulk edits, forms, credential preservation, save failures/retry, DNS summaries/filter parameters, stale checks, deletion retry and desktop/mobile themes.

## Applying the package

Extract the archive into `tycdn-backend` preserving paths. Upload the included application source files and the entire `public/build` directory, then run `php artisan optimize:clear` and hard-refresh the browser. The new site-resource controller and updated API routes must be uploaded together with the frontend assets. Earlier console updates are included.

No SQL or database migration is required. This package is not deployed automatically.
