# Console node management

The console node page now follows the master panel's four-tab structure: 节点列表, 待初始化, 同步失败已禁用, 区域管理. The list uses the master's column order, compact toolbar, expandable secondary IPs, and bottom-left pagination with 10/30/100/300 rows per page. Colours use the console's existing light/dark theme and #2d8cf0 primary button colour.

## Data and actions

- Verified both live read APIs on 2026-09-23: six parent nodes, one region, no pending nodes or sync-disabled nodes. These counts and records are not embedded in application code.
- Node lists request `sub-ip=1`. Secondary IPs are grouped by `pid`; the upstream parent-node count drives pagination.
- Search, region, enabled state, and L1/L2 type use the master's query fields. Sync-disabled nodes use `enable=0&disable_by=sync_error`.
- Bulk enable/disable and deletion use the existing console APIs. Bulk writes run sequentially; failures identify the affected IDs and preserve their selection for retry.
- Secondary-IP enable/disable uses an IP update without `target=node`.
- Pending-node initialization/deletion and region creation/editing/deletion reuse the existing API and dialogs. Numeric form values accept both strings and numbers; a region sort value of zero is preserved.
- IP availability logs query the selected IP with `type=aval`, start/end timestamps, and pagination, matching the master's request.
- Installation commands load only when the install dialog opens. Tab data loads on demand. Stale responses cannot replace a more recent query.
- Line-group management remains at `/console/admin/line-groups`. Links from a node pass its ID into the group query. Existing node-edit, secondary-IP, and topology dialogs remain available.

This update covers the four node-management tabs and the actions above. It does not recreate every advanced configuration dialog in the master panel.

## Validation

TypeScript checking, ESLint, 32 JavaScript contract tests, 23 PHP node/installation tests (79 assertions), and the production build. Browser checks use sanitized records from the master and isolated fixtures for writes, pending nodes, disabled nodes, failures, and region changes. Screenshots cover light/dark themes and mobile sizing. Production mutations were not performed.

## Deployment

`tycdn-node-management-console.zip` contains backend-relative source paths and the complete `public/build` output, including its matching manifest. Extract it into the existing `tycdn-backend` directory, keeping the asset files and manifest together. Refresh the browser after deploying.

No SQL, migrations, environment changes, or backend route changes are required. The package is prepared locally; it has not been deployed to console.tycdn.org.
