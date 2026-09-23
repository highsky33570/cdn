# Node monitoring settings

Updates `/console/admin/config/node-monitor` to the master panel's three-tab layout: 监控配置, 通知配置 and 切换日志.

- Monitoring: enable switch; HTTP/TCP/PING protocol; conditional HTTP host/path and port fields; timeout; monitoring groups; action; interval; failure and bandwidth thresholds.
- Notifications: all-day/custom hours, email/SMS channels, event subscriptions, recipients, all ten email templates and SMS template IDs. SMS copy converts placeholders according to the configured provider (Aliyun, Tencent, SMSBao or Submail).
- Switch logs: type/action/IP/group/node/line filters, native columns, pagination and per-channel notification results.
- Light/dark themes and responsive layout; data comes from the master API, including templates.

## Saving and data

Settings save automatically when selections change or text/number fields are committed. Requests are serialized, and rapid changes queue the latest snapshot to avoid out-of-order overwrites. Failed saves retain the draft and offer retry. Loading the page does not save anything. Invalid configuration or threshold values block writes.

The existing `PUT /api/admin/configs` saves the global `system/node_monitor_config` row. Unedited values and unknown fields are preserved. Monitoring groups remain comma-separated, notification channels/events remain space-separated, and numeric fields are sent as numbers.

`GET /api/admin/workspace/ip-switch` is added to the existing admin workspace resource map and proxies the native `GET /v1/log/ip-switch`. Other methods are rejected.

## Verification

Read-only review of the live master and console confirmed the actual monitoring settings, notification templates, SMS provider and an empty switch-log list. The live master application bundle confirmed field names, values, autosave behavior, SMS conversions and the log endpoint.

- Production build, Vue types, ESLint, formatting and PHP formatting passed.
- Three JavaScript tests cover round trips, hidden-field preservation, validation and SMS conversions.
- Nine backend/config tests passed (27 assertions).
- Browser checks passed: 29 API requests, 15 local mock writes, maximum one write in flight, no browser errors. Covered rapid edits, failure/retry, protocol-dependent fields, channels, templates, clipboard conversion, all log filters, pagination, notification details, loading failures and light/dark/mobile layouts.

No production monitoring settings were changed during validation. Nonempty log cases used local test fixtures.

## Applying the package

Extract the archive into `tycdn-backend`, keeping its paths. Upload the changed PHP controller, frontend sources and complete `public/build` directory together; the manifest must match the included assets. Earlier DNS, L2 and line-group changes are included to keep the current frontend build and source consistent.

Run `php artisan optimize:clear` from the backend directory and hard-refresh the browser. No SQL or database migration is needed. Tests and README files are optional on the server. The package has not been deployed automatically.
