# Console stream monitoring

`/console/admin/streams/analytics` now uses a dedicated administrator page matching the master's `/dashboard/monitor/stream/realtime` layout and query workflow.

## Behavior

- **带宽流量** opens by default, with separate bandwidth and traffic charts side by side. Both use the same submitted port and time range.
- Port input supports the master's space-separated protocol notation, such as `88/TCP 99/UDP`.
- The default range is the last hour. Presets cover 1, 6 and 12 hours; custom start/end inputs preserve seconds. Invalid or reversed ranges do not send requests.
- **端口排行** provides 10-minute, 30-minute and 1-hour windows, a refresh button, and the master's four columns: rank, port, connections and traffic. Numeric sorting preserves zero values and places missing values last.
- Ranking requests now use the administrator API. The previous shared page called the customer ranking endpoint even in administrator mode, and loaded unrelated customer package/group data.
- Charts render locally without an external chart CDN. Light/dark colors, hover values and responsive sizing use the console theme. Directional inbound/outbound responses and legacy point arrays are supported.
- Empty responses show empty charts with the selected date range, without the master's artificial epoch-zero point. Errors remain visible, and either chart can be retried independently. Older responses cannot overwrite a newer query.
- There is no background polling, matching the inspected master page. Queries run on initial load, tab/window selection, port changes or explicit query/refresh actions.

## Existing API contracts

| Console endpoint | Master endpoint | Parameters |
| --- | --- | --- |
| `GET /api/admin/workspace/stream-realtime` | `GET /v1/monitor/stream/realtime` | `type=stream-bandwidth` or `stream-traffic`, `start`, `end`, `port` |
| `GET /api/admin/workspace/stream-top` | `GET /v1/monitor/stream/top` | `type=top-ports`, `recent_time=10m`, `30m` or `60m` |

Time parameters retain `YYYY-MM-DD HH:mm:ss` in the browser's local time, as in the master. Chart timestamps are Unix milliseconds. The master supplies bandwidth in bytes per second; display converts to bits per second with the master's Kbps/Mbps/Gbps thresholds. Traffic uses decimal KB/MB/GB. Rankings read the native `res`, `count` and `traffic` fields and use decimal byte units.

The existing administrator middleware, backend endpoints and upstream credentials are unchanged. No production database or PHP code changes are required.

## Evidence and validation

Authenticated read-only checks of both live applications on 2026-09-22 returned HTTP 200 and empty data for both stream metrics and the 10-minute port ranking. The live master stream JavaScript matched the local panel asset SHA-256 `7214a629af1f2338523338c821c5e06f9533598df9cd62508a644fec53c37493`. Its actual requests, units and field names were inspected.

The application uses only real API data. Populated chart/ranking examples are isolated test fixtures; no synthetic records are included in the application or sent to either live server.

Validation:

- 28 JavaScript contract tests, including stream ranges, byte/bit units, native rankings, zero/missing values and legacy/directional samples.
- 5 PHP integration tests with 28 assertions covering master credentials, exact query forwarding, empty/directional data, upstream errors and administrator authorization.
- TypeScript, targeted ESLint, PHP formatting and production build.
- Local Chrome checks for both tabs, live empty response rendering, populated fixtures, every time control, multi-port filtering, tooltips, sorting, errors/retry, stale responses, deep links, themes and mobile overflow.

No production writes were performed.

## Deploy

`.audit/console-validation/tycdn-stream-analytics-console.zip` contains the changed Vue/TypeScript source and tests, this document, and the complete compiled `public/build` directory. Archive paths are relative to the Laravel backend directory containing `artisan`.

1. Extract into that backend directory, merging included files. Upload the compiled manifest and assets together, retaining old hashed assets while existing tabs may reference them.
2. Refresh `/console/admin/streams/analytics` and check both tabs. Empty charts/rankings remain expected until the master reports stream traffic.

No SQL, migrations, dependency installation or server-side frontend build is required. The update is prepared locally and has not been deployed to the live console.
