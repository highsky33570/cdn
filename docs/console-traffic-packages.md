# Traffic package workspace

Updates `/console/admin/workspace/traffic-packages` and the direct sold-package route `/console/admin/workspace/user-traffic-packages` to the supplied reference layout. The workspace uses the project table, tabs, buttons, inputs, selects, switches, dialogs, pagination, and theme colors.

- Management tab: add, bulk enable/disable/delete, compact package/traffic/price/scope/status columns, and pagination.
- Add/edit dialog: name, notes, GB/TB/PB selector, multiple applicable packages, validity in days, price in the project's USDT currency, and status.
- Sold tab: user lookup, traffic-package and status filters, bulk enable/disable, applicability and dates, and existing detail/edit/delete actions. Assignment remains available from a management row.
- Requests retain native CDNfly fields. Bulk status updates send arrays; sold-package disabling includes the required manual reason. Empty notes and package restrictions remain explicit empty strings through the Laravel proxy.
- Saves validate numeric values, preserve drafts on failure, and expose retry controls for failed reads. Failed deletions retain only the remaining IDs for retry.

## Verification

- Production Vite build, Vue type checking, ESLint, Prettier, and PHP formatting passed.
- Three backend tests / 15 assertions cover native payloads, filters, administrator permissions, and explicit clearing.
- Browser checks using local API fixtures passed: 53 requests, eight simulated writes, no page errors. Coverage includes creation, editing, assignment, clearing restrictions, status/deletion actions, filters, pagination, failed requests and retry, both entry routes, empty states, dark theme inheritance, and mobile overflow.
- Screenshots were visually reviewed. No live production data was changed.

## Deployment

Extract `tycdn-traffic-packages.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The cumulative archive includes prior console updates, current source, tests, and compiled assets with their manifest. No database migration is required. Deployment has not been performed automatically.
