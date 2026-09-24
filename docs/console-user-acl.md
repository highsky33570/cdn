# User ACL list layout

Updates `/console/security/acls` to the supplied screenshot.

- Flat white workspace with 添加ACL and 更多操作, followed by status, ACL name and ACL ID filters and 清除.
- Selection / ID / 名称 / 备注 / 状态 / 操作 columns, compact empty state, 48 px header, 60 px rows, and left-aligned numbered pagination starting at 10 rows.
- Existing ACL editor remains connected. Editor labels now consistently say ACL. Nested rule data is preserved when editing.
- Existing customer `/v1/waf-rules` API remains the source of ACL records. Supports per-row and bulk enable/disable/delete, deletion confirmation, failed-selection retention, debounced filtering, retry and stale-response protection. Global ACLs remain read-only.
- Responsive filters, horizontal table scrolling, a visible mobile empty state and dark theme.

Validation: production build, Vue/TypeScript check, ESLint, whitespace check, ACL browser verification using compiled assets, and regression browser checks for the shared CC editors. ACL checks cover filters, pagination, existing nested rules, create/edit validation, failed saves, bulk partial failures, delete/cancel, retry, stale requests and desktop/mobile/dark layouts. No production data was changed.

## Apply

This cumulative ZIP includes the previous CC, cache, certificate, sites, analytics, sidebar and admin updates. Extract into `tycdn-backend`, replacing matching files, then run:

```sh
php artisan optimize:clear
```

Compiled assets are included. No database migration is required. This package has not been deployed by the assistant.
