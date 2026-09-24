# Package monitor layout

Updates `/console/admin/workspace/package-monitor` to the supplied reference layout while retaining the project components and theme colors.

- Sorting selector, user ID, comma-separated user-package IDs, and node IDs share one responsive toolbar.
- The shared table combines current bandwidth/limit and current connections/limit, with a details action and numbered pagination.
- The node distribution dialog uses the shared table with four columns: node ID, bandwidth, connections, and report time. Refresh fetches the selected package again.
- Native query names are preserved: `order_by`, `uid`, `upids`, `node_ids`, and `upid` for node details. The API's mutually exclusive user/package filters are validated before requesting data.
- Bandwidth converts bytes/second to decimal Mbps/Gbps. A limit of -1 displays as unlimited; missing readings display as a dash rather than zero. Epoch report times use the browser's local time zone.
- Loading, empty, and retry states are supported. Request tokens prevent responses from a previously selected package from replacing the current dialog.
- Shared table and pagination extensions are optional; existing consumers retain their defaults.

## Verification

Production Vite build, Vue type checking, ESLint, Prettier, and whitespace checks passed. Browser fixture checks passed with 18 requests, no writes, and no page errors. They cover sorting, filter parameters, validation, numbered pagination, page sizes, unit conversion, unlimited/missing values, node refresh, retry, empty results, stale-response handling, dark theme inheritance, and mobile overflow. Desktop and mobile screenshots were visually reviewed.

No production requests or data changes were used for verification.

## Deployment

Extract `tycdn-package-monitor.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The cumulative archive includes previous console updates, current source, and compiled assets with their manifest. No database migration is required. Deployment has not been performed automatically.
