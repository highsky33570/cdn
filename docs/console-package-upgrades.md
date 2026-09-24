# Upgrade package workspace

`/console/admin/package-upgrades` now follows the supplied layout with **升级包管理** and **已售升级包** tabs. The tables, empty states, selection, buttons, dialogs, and form controls use project components and theme colors. Desktop and mobile layouts retain light/dark theme support.

The management table groups each upgrade's name/ID/bindings, type/quantity/monthly price, status, and actions. The editor contains name, notes, type, quantity, price, multi-select base package bindings, and enable. It reads native upgrade details before editing and submits the native `type`, `amount`, `price`, and `bind_package` fields. Existing upgrade types remain immutable; WAF upgrades have quantity one. Monthly prices use the portal's existing USDT display convention.

Selected upgrades can be enabled/disabled in one native batch request or deleted after confirmation. Assigning an upgrade uses the shared remote user picker and only lists that user's packages. The sold tab supports remote user search and upgrade filtering, native sold-record quantity edits, and confirmed deletion. API failures stay visible and save failures preserve edits. The upstream master retains responsibility for package ownership, binding eligibility, and deletion constraints.

The previous generic upgrade form embedded in basic package management has been replaced with the same workspace, so both entry points use the native form fields.

## Validation

- Production Vite build, Vue TypeScript, ESLint, Prettier, PHP formatting, and whitespace checks.
- Five backend tests / 39 assertions cover administrator access, native paths and filters, batch payloads, assignment, sold-record updates/deletion, invalid input, and upstream failure.
- Local browser fixtures exercise pagination, create/edit, multiple package bindings, failed read/save retries, WAF quantity, batch status and deletion, assignment, sold filters/quantity/deletion, empty tables, dark theme inheritance, and mobile overflow.

Browser writes were mocked. No production records were changed or live deployment performed.

## Deployment

Extract `tycdn-package-upgrades.zip` into the deployed `tycdn-backend` directory, preserving paths. Then run:

```sh
php artisan optimize:clear
```

This cumulative archive contains the earlier console changes, updated source/routes/controller/tests, and current compiled assets and manifest. No database migration is needed.
