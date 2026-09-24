# User forwarding layout

Updates `/console/streams` to the supplied screenshots.

- Flat white workspace with 转发列表 / 分组管理 / 默认设置 tabs.
- Compact add, batch-edit, more-actions, search and advanced-search toolbar.
- Wide forwarding table ordered as selection, ID, listening port, origin, CNAME, package, group, status, notes, creation time and actions. Group and default-setting tables match their reference columns.
- Compact empty states and left-aligned numbered pagination starting at 10 rows.
- Existing add/detail and batch-create flows remain connected. Creation validates port ranges and reloads group choices when opened.
- Customer-scoped group and default-setting create/edit/delete operations use existing `/v1/stream-groups` and `/v1/user-configs` APIs; defaults always use `type=stream`. Default values support listening protocol, balancing mode and Proxy Protocol, with global or group scope.
- Per-row and bulk enable/disable/delete actions, deletion confirmation, and partial-failure selection retention. Batch editing sends only selected fields. Failed editor saves preserve the form.
- Responsive controls, horizontal list scrolling, dark theme and keyboard tabs.

Validation: production build, Vue/TypeScript check, ESLint and local browser verification against compiled assets, covering tabs/columns, scrolling, pagination, search, bulk failures, group/default CRUD, zero-valued defaults, creation, retries, stale responses and responsive layouts. No production data was changed.

## Apply

This cumulative ZIP includes the preceding ACL, CC, cache, certificates, sites, analytics, sidebar and admin updates. Extract into `tycdn-backend`, replacing matching files, then run:

```sh
php artisan optimize:clear
```

Compiled assets are included. No migration is required. This package has not been deployed by the assistant.
