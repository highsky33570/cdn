# DNS console update

`/console/admin/dns` now matches the master panel's DNS page with two tabs: DNS配置 and CNAME域名.

- Compact DNS form with provider-specific credential labels, TTL, IP-weight switch, DNS task error status, save, record repair, and cleanup actions.
- CNAME table with selection, ID, domain, remark, edit/delete actions, domain search and pagination. Add/edit dialogs use real API data. Bulk deletion keeps failed items selected for retry.
- Light/dark themes and mobile layout. Credentials can be revealed using the eye button.
- Certificate DNS API management remains in the existing certificate/site workflow rather than a third tab on this page.

## API integration

Existing DNS save and CNAME CRUD endpoints are retained. Two admin-only endpoints are added:

- `GET /api/admin/dns-setting/status`: reads `/v1/configs/global-0-system-record_repair`, including state, task result and task ID. Failed status requests show an error instead of a healthy badge.
- `POST /api/admin/dns-setting/repair`: accepts `mode: 1` for repair or `mode: 2` for cleanup, then updates the master's global `record_repair` configuration using `PUT /v1/configs`, exactly as the native panel does. Task submission is reported separately from task completion.

Empty CNAME remarks are normalized to an empty string so existing remarks can be cleared.

## Validation

Read-only checks against the live master and console confirmed Cloudflare, TTL 600, IP weighting enabled, one CNAME domain, and a completed DNS task with no error. The live master's JavaScript bundle confirmed provider labels and the two repair command values. Credentials were replaced with test values before saving browser fixtures.

Vue type checking, ESLint, formatting, production build and PHP formatting passed. DNS/CNAME feature tests: 22 passed, 63 assertions. Browser checks: 28 API requests, 10 local mock writes, no browser errors. Covered settings save, provider labels, weighting, task commands, failed status reads, failed cleanup retry, CNAME search/add/edit/delete, clearing remarks, partial bulk-delete retry and light/dark/mobile layouts. No production DNS changes were performed during validation.

## Apply

The ZIP paths are relative to `tycdn-backend`. Upload the included PHP files, routes, frontend sources and the complete `public/build` directory together. The manifest must accompany its assets. The archive also retains the preceding line-group and L2 source updates so the current build can be reproduced.

Run `php artisan optimize:clear` from the backend directory, then hard-refresh the browser. Tests and README files are optional on the server. No SQL or database migration is required. This package has not been deployed automatically.
