# Node realtime monitoring

Updates `/console/admin/node-monitoring` to match the master panel's `/dashboard/node/realtime` layout.

- Resource rankings: bandwidth, connections, system load and disk usage; 1/5/30/60-minute windows; native columns, numeric sorting, node names/IDs and readable units.
- Monitoring metrics: 1/6/12-hour and custom ranges, node selection, stacked interface/partition charts, named series, interactive legends and hover values.
- Refreshing monitoring metrics or changing the time range keeps existing chart panels and SVG elements mounted, updating values and axes in place. Legend selections persist, and failed refreshes retain the last successful chart data with an error and retry control. Changing the node or metric clears the previous selection's charts.
- Node traffic: outbound/inbound selection, 1/7/30-day and custom ranges, node selection, excluded interfaces and total traffic.
- Light/dark themes and mobile layout. Direct links with `?node_id=…` open monitoring for that node.

## Data

Live read-only checks of the master and console verified all four ranking and realtime response formats and the node-traffic response. The master frontend bundle was also checked for parameters and units. The page uses existing authenticated API proxies; no server API changes are required for this page.

Realtime bandwidth and disk responses contain separate named series per interface/partition. CPU, memory and load samples can contain `{value: number}` values. Both formats now render correctly, including zero samples. Node-traffic `bytes_sent` and `bytes_received` are already measured in MB. Selected directions and matching timestamps are summed without incorrectly converting MB to bytes. No live values are hardcoded.

Request failures show errors and retry controls. Invalid custom ranges do not send requests, and stale responses cannot overwrite a newer selection. Refresh is manual.

## Verification

- Production build, Vue type checks, ESLint and formatting.
- Five JavaScript regression tests covering real response shapes, zero samples, object-valued metrics, partitions, traffic units and empty responses.
- Browser checks using captured live responses: all tabs and metrics, numeric sorting, ranges and validation, interface exclusion, direction totals, legend controls, hover values, request races, failure/retry, node deep links, light/dark and mobile views. 27 requests, no browser errors; no production writes.
- Refresh regression checks additionally verify identical panel, SVG and polyline DOM elements during a delayed response and after changed values, time-range changes, failures and retry. The expanded browser run passed with 30 requests and no browser errors or production writes.

## Applying the package

The archive contains the changed sources and complete compiled `public/build`, plus the earlier line-group, L2, DNS and monitoring-settings changes. Extract into `tycdn-backend` preserving paths. Upload the included application files and entire `public/build` together so the manifest and assets remain consistent. Test files and README files are optional on the server.

Run `php artisan optimize:clear` from the backend directory, then hard-refresh the browser. No SQL or database migration is required. This package has not been deployed automatically.
