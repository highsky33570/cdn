# Node edit dialog

The node-name action in `/console/admin/nodes` now opens the master's compact five-tab editor: 基本设置, 节点设置, IP归属, 自动禁用 and 添加子IP.

Each tab saves independently:

- Basic: name, remark, sort, IP and L1/L2 type. Does not change enable state or limits.
- Node settings: cache directory, cache capacity in GB and log directory. Placeholders come from the actual global/region Nginx configuration. Values save to the node-scoped `nginx-config-file`; unrelated Nginx settings are retained. Clearing a field removes that node override.
- IP location: country, province, city, ISP and country code (`areacode`). Reads native JSON and sends an object.
- Auto-disable: Mbps/Gbps limit, traffic enable switch, monthly day/time, GB allowance, selected directions, excluded interfaces and disabled time periods. Uses the verified native `traffic_limit.type` array and numeric/boolean fields.
- Sub-IPs: one address per line, submitted through the existing sub-IP API. Duplicate lines are collapsed.

The full node detail is loaded on open. Failed reads block editing; failed saves preserve the draft. Opening or switching tabs does not write to the master. Configuration reads and node detail were verified against the live master using read-only requests. Production node settings were not modified during testing.

## Validation

Production build, Vue type checks, ESLint, formatting, two JavaScript regression tests and 15 backend tests passed. Browser checks using captured live data passed all tabs, eight local mock write attempts, independent payloads, override removal, unrelated-config preservation, input validation, failure/retry, dark mode and mobile layout.

## Installation

Extract the update archive into `tycdn-backend` preserving paths. Upload the included PHP controller and frontend sources together with the complete `public/build` directory. The archive also includes earlier console updates to keep the compiled build and sources consistent. Tests and README files are optional on the server.

Run `php artisan optimize:clear` and hard-refresh the browser. No SQL or database migration is required. The archive has not been deployed automatically.
