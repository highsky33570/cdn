# Package management layouts

Updates `/console/admin/packages` and `/console/admin/sold-packages` to the supplied reference layouts. All tables use `ConsoleDataTable`; tabs, dialogs, buttons, form fields, backgrounds, and borders use the existing project components and theme tokens.

## Basic packages

- Basic package and package group tabs, toolbar actions, group filter, selection, and pagination.
- Compact package, region/lines, resources, capabilities, price, enable, and action columns.
- Scrollable package editor with basic information, limits, capabilities, pricing, and expandable advanced configuration. Header/footer remain visible. Portal product linkage and allocation flows are retained.
- Group editor includes name, description, sort, and enable. Batch enable/disable, synchronization, and confirmed deletion retain failed selections for retry.
- Existing native prices and DDOS text are preserved when editing packages without a linked portal product.
- Completion review: added the Mbps/Gbps selector, expiry date/time picker, region-aware line choices, and the reference purchase-limit field arrangement. Limit switches remember their entered value while toggled off and on.
- Creating a package uses its name for the linked storefront product when the optional sales section is left collapsed. Prices use one set of fields for native and storefront saves; disabling storefront sales now deactivates the linked product without changing prices.
- Clearing descriptions, backup lines, expiry, CNAME hostnames, assigned users, and source IP restrictions is explicitly submitted. Invalid prices, blank enabled limits, and duplicate primary/backup lines are rejected before saving.
- Failed storefront lookups expose retry controls and prevent unsafe editing with missing linkage data. If the native edit succeeds but storefront synchronization fails, the dialog reports the partial save and retains the form for retry.

## Sold packages

- Dedicated workspace with native expiry/base-package/order/status/traffic filters and user ID, package, and CNAME search.
- Shared table showing package/user, base package, purchase/expiry dates, used/total traffic, status, and details/edit/upgrade actions.
- Detail dialog with summary, five resource cards, usage table, and package information. Totals include purchased upgrades; unlimited quotas remain unlimited. Missing or failed usage data is not displayed as zero.
- Editor sections cover line groups, resource limits, capabilities, expiry/renewal prices, and CNAME. Only changed fields are submitted.
- Upgrade dialog shows purchased and available upgrades, quantities, confirmed removal, and native price quotes before changing the base package. WAF upgrades are limited to one unit, matching the native panel.
- Existing native update/delete routes handle row and selected-row actions. New administrator-only endpoints read detail/quotes and usage and add upgrades with the native `package_up` / `amount` payload.

## Verification

- Production Vite build, Vue TypeScript, ESLint, Prettier, PHP formatting, and whitespace checks.
- 10 backend tests / 64 assertions for package management, administrator access, native routes/payloads, validation, and upstream failures.
- Four resource calculation tests for purchased quantities, resource isolation, unlimited/over-limit usage, missing/malformed data, and response envelopes.
- Browser checks with local API fixtures cover list columns, filters, pagination, create/edit, group fields, synchronization, upgrade changes, failed saves/reads and retry, dark theme inheritance, and mobile overflow. No production mutations were used for verification.
- Completion review: package-management and product-link suites passed (21 tests, 104 assertions across the final runs). The browser completion check passed with 22 requests, six simulated writes, and no console errors, including collapsed-section creation, explicit clearing, price preservation, region filtering, bandwidth units, expiry, and failed-save retry. Production build, Vue type checking, ESLint, and PHP formatting also passed.

## Deployment

Extract `tycdn-packages-complete.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The cumulative archive includes prior console updates, current source/controller/routes, tests, and compiled assets with their manifest. No database migration is required. This archive has not been deployed automatically; production integration still needs checking after deployment.
