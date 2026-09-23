# Admin service overview

`/console/admin` now follows the master `/dashboard/admin-home` layout: operating charts, network overview, monitoring trend, and TOP10 on the left; administrator identity, system status, license, and usage statistics on the right. The page supports light/dark themes and stacks on mobile.

## Verified data contracts

Read-only verification against the live master and console APIs was performed on 2026-09-23. Application code contains no captured measurements or resource counts.

- Registered users: `GET /v1/new-user/count`, native `time` / `count` rows.
- Packages opened: `GET /v1/package-sold/count`, native `time` / `count` rows.
- Recharge: `GET /v1/order/count` with `limit=0`, `state=已付款`, `type=充值`, `group_by=day`. Native numeric `sum` values display as USDT; no conversion is applied.
- Network totals: `GET /v1/monitor/usage-count` with calendar dates and an exclusive end boundary. The master's near-7/near-30 controls include seven/thirty prior days plus today; the console matches these boundaries.
- Trends: `GET /v1/monitor/usage`, using `bandwidth`, `req`, `traffic`, or `blackip` and native `date` / `value` samples.
- Bandwidth is returned in bytes/second and multiplied by eight for network-rate display. Traffic uses decimal units. Missing values display as unavailable; real zeros remain zero.
- TOP10: `GET /v1/monitor/site/top`, `recent_time=30m`, with domain, URL, IP, and country types. The first ten upstream rows retain their ranking.
- System and usage: `GET /v1/admin/overview`.
- License: `GET /v1/common/auth`, using `nodes` and `end_at`; `nodes=-1` means unlimited.
- Account: the configured master's `GET /v1/user` identity, restricted by the console endpoint to `id` and `name`. Login history is scoped to that master account, not the local console user's ID. Like the master, the preceding login is used when two records are available.

“立即检查” uses `POST /v1/maintain/agent-check`; “刷新授权” uses `POST /v1/common/auth`. These actions only run after the user clicks the relevant button. Both remain administrator-only and discard unexpected request fields. Agent-check results are refreshed with bounded polling and cancelled on navigation.

Independent request state prevents failed sections from hiding healthy ones and prevents stale responses from overwriting newer selections. The former stacked local-business dashboard is replaced by this master overview; the underlying local APIs and management pages remain available.

## Verification

- TypeScript and ESLint passed.
- Five dashboard adapter tests passed, covering date boundaries, units, USDT, and missing/zero samples.
- Fifteen PHP dashboard/navigation/usage tests passed (182 assertions).
- Production build passed.
- Browser checks passed with live read fixtures and isolated action mocks: initial values, period independence, metric/ranking tabs, stale responses, request failures, unlimited licenses, empty data, zero values, and light/dark/mobile rendering. No production actions were executed.

## Deployment

Extract `tycdn-admin-dashboard-console.zip` into the existing `tycdn-backend` directory. It contains the changed PHP controller, frontend source, tests, and the complete compiled `public/build` directory with its matching manifest. Deploy the PHP controller together with the assets because the charts and buttons use the new workspace resources.

No SQL, migrations, environment changes, or route-cache changes are required. Reload PHP workers if the server's deployment process requires it, then refresh the browser. This package has been prepared locally and has not been deployed to the live console.
