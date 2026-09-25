# Shared buttons throughout the console

Replaced 306 native button elements in 55 Vue files with `Button` imported from `@/components/ui/button`. This covers user/admin console pages, table actions, toolbar actions, tabs, chart controls, shared account/header controls, the date-range picker and the sidebar rail. The standalone marketing frontend is separate from this console application.

Primary, outline, ghost and link variants preserve each control's purpose. An `inline` size supports text links and compact embedded controls without adding toolbar padding. Existing console action dimensions and the unified pagination layout remain in place. Calendar cells retain their grid dimensions.

Event handlers, disabled states, ARIA attributes, dropdown `as-child` composition, explicit form associations and native implicit submit behavior are preserved. The form-control audit now rejects native buttons in console application pages/components. UI primitives continue to own native form elements internally.

## Validation

- No native `<button>` tags remain in `tycdn-backend/resources/js` Vue templates.
- Custom-form audit, TypeScript, changed-file lint/format checks and production build.
- Eleven mocked customer workflow suites: ACL, CC rules, streams, sites, certificates, cache, orders, account logs, messages, subscriptions and API keys.
- Checks cover submissions, bulk actions, disabled states, dropdown triggers, copy actions, filters, date presets/manual date selection, retries, light/dark styling and mobile layouts.
- A separate browser survey covers 22 user/admin console views and confirms the unified pagination layout remains intact.

All browser API calls use local fixtures. No production data or deployments were changed. The production build still reports existing CSS `:deep` warnings.

## Installation

Extract the cumulative update ZIP into `tycdn-backend`, preserving environment configuration, then run:

```sh
php artisan optimize:clear
```

The archive includes matching `public/build` assets and all previous console updates.
