# Node monitoring log dialog

The `[日志]` action in the node list now opens a compact 414px 监控日志 dialog matching the provided reference: three aligned filter rows, small table rows, numbered pagination and a page-size selector.

The four log views use the master's actual queries:

- 可用性监控日志: `node-ip-log`, `type=aval`, IP, start/end and optional `group_id`.
- 可用性切换日志: `ip-switch`, `type=节点IP解析`, selected `node_id`.
- 带宽监控日志: `node-ip-log`, `type=bw`, IP, start/end and optional `group_id`.
- 带宽切换日志: `ip-switch`, `type=带宽监控`, selected `node_id`.

Monitoring logs show 检测时间 / 失败个数 / 总检测点. Switch logs show 切换时间 / 动作 and hide the monitoring-only filters, matching the master. The monitoring groups use the master's IDs (domestic 1, telecom 3, unicom 4, mobile 5, overseas 2).

The combined time-range control opens editable start/end date and time fields. Filters reset pagination. Opening a different node resets the modal; old requests cannot replace newer results. Clicking the table or pressing Enter while it is focused refreshes the current page. No records or counts are hardcoded.

## Validation

Read-only master requests confirmed all four endpoints, with 111 availability records at the time of review and empty bandwidth/switch results. Production build, Vue types, ESLint and formatting passed. Browser checks passed for selectors, group IDs, date validation, pagination, page size, query scoping, request races, errors/retry, another node, and light/dark/mobile layouts: 18 requests, zero writes, zero browser errors. Pagination tests reuse captured records locally to exercise additional pages.

## Installation

Extract the archive into `tycdn-backend`, preserving paths. Upload the included sources, PHP files and complete `public/build` together. Run `php artisan optimize:clear`, then hard-refresh. Tests and README files are optional on the server. No SQL or database migration is required; this update has not been deployed automatically.

The package includes previous console changes and the console translation opt-out removal. The separate website translation update remains in the earlier browser-translation package.
