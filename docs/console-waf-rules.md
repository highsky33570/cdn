# Console WAF rule library

The console route `/console/admin/security/waf` now follows the panel WAF library reference: a compact title and count, create action, name/scope/status filters, subscription and batch controls, ten table columns, and right-aligned pagination (10 rows per page). The layout supports dark mode and horizontal table scrolling on small screens.

Data comes from the existing WAF library API. An explicit empty `system_key` is preserved through the console API to exclude the built-in library, matching the panel's list and count. Disabled status `0` remains a valid filter. Reads are guarded against stale responses; failed batch changes retain the affected selection for retry.

Editing loads complete library details before opening the existing JSON rule editor, preserving unknown rule fields. The existing create, update, and confirmed delete flows remain available. System libraries do not show delete controls. Subscription updates require selected, enabled, global libraries with subscriptions enabled and an address configured. The master returns changed, unchanged, and failed counts.

New administrator-only API routes:

- `GET /api/admin/acls/{id}` forwards a numeric library ID to `/v1/waf-rules/{id}`.
- `POST /api/admin/acls/update-subscription` forwards a validated, nonempty list of positive distinct IDs to `/v1/waf-rules/update-subscription`; write throttling applies.

Validation: production Vite build, Vue TypeScript check, ESLint, Prettier; 18 targeted backend tests / 70 assertions including the native WAF contract tests. Browser checks passed with 26 local requests, 8 mocked writes, and zero page errors, covering empty/populated states, filters, pagination, batch partial failure and retry, subscription eligibility, full-detail editing, create failure/retry, confirmed deletion, load failure/retry, dark mode, and mobile overflow. Live master access was read-only; no production WAF settings were changed.

## Deploy

Extract `tycdn-waf-rules-console.zip` into the deployed `tycdn-backend` directory, preserving paths, then run `php artisan optimize:clear`. The archive includes cumulative earlier console updates, current compiled assets and their manifest, source files, and backend changes. No database migration or SQL is required. This package has not been deployed automatically.
