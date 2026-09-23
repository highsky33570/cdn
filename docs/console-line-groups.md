# Line groups layout

`/console/admin/line-groups` now uses a dedicated compact page based on the master panel's line-group list and dialogs.

- Toolbar: add group, selected-group deletion, region filter, name/resolution search, and clear.
- Columns: selection, ID, name, region, resolution value and IPv4 alias, node/site/stream counts, L2 configuration, sort, and actions.
- Pagination: total, page controls, and 10/20/50/100 rows per page. Existing node-scoped links remain supported.
- Add dialog: region, name, resolution value (blank generates upstream), remark, and sort (default 100).
- Edit dialog: existing basic fields, IPv4 alias, L2 configuration, and expandable backup-IP settings. Unchanged optional settings are preserved. Zero sort is valid.
- Configure resolution opens the existing DNS/node assignment interface for that specific group; unrelated region/group lists are not shown in the dialog.
- Bulk deletion preserves failed selections and retries only failed IDs.

The controller now accepts the master's `gt_online_ip` failover mode and forwards explicitly cleared optional edit fields, including a null L2 selection.

Verified with read-only live master/console responses: hk-line and jp-line, their aliases, counts, regions, and sort values. Browser validation uses local copies of these responses; all create/edit/delete checks use local mock APIs. No live groups were changed.

Validation: 14 PHP tests / 47 assertions; TypeScript, ESLint, formatting, production build; browser checks for add/edit, filtering, page size, group-specific DNS resolution, partial deletion failure/retry, error/empty states, light/dark themes and mobile width.

## Install

Extract `tycdn-line-groups-console.zip` into the existing `tycdn-backend` directory. Upload the included `app/Http/Controllers/Api/AdminNodeController.php` and the complete `public/build` directory together, including its manifest and hashed assets. Updated source and tests are included for future builds.

No SQL or database migration is required. The update has not been deployed automatically.
