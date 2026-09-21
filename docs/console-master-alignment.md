# Console alignment — 2026-09-21

The console now uses the reviewed CDNfly master navigation and page structure, while retaining the portal's real API records and existing USDT / ¥ presentation.

## Implemented

- Separate global administrator and personal navigation, header account controls, compact tabs, rounded cards, and shared light/dark tokens for menus and dialogs rendered outside the page shell.
- A shared seven-tab website editor for administrator and personal scopes. Editing preserves multiple origins, weights, unknown nested fields, HTTPS settings, cache order, CC rules, WAF libraries and allow rules, headers, rewrites, and advanced settings. Disabling listeners sends an empty object, as required by the master.
- The legacy site-group route now opens real group management. Unknown routes no longer render demonstration data.
- Global overview and analytics use administrator credentials. Node charts, traffic records, rankings, WAF attack logs, and package monitoring use their native master fields and time parameters. Empty data remains empty.
- Dedicated navigation and API-backed forms for L2 configuration/conditions/node bindings, traffic packages and assignments, discounts, coupons, announcements, and tasks. Task cancellation uses the master's enable flag.
- Grouped configuration forms with masked secrets, preservation of unchanged secrets on the server, scoped editing, and creation of default settings. Personal defaults use record IDs when updating.
- Package lists show region/line information and fuller limits. Source-IP restrictions have a dedicated editor. Node lists show health checks, live bandwidth, monthly traffic, sorting, and monitoring links.
- Forwarding supports batch creation with preview, validation, and individual results, plus enable/disable actions for selected records and dedicated groups/defaults pages.
- Finance counts failed services separately from failed orders. Mapping recovery previews the existing remote account and prevents duplicate bindings; changing mappings discards credentials associated with the old identity. Failed provisioning has a review/retry action using the existing provisioning flow.

## Validation

- 81 PHP feature/contract tests passed (378 assertions).
- 12 JavaScript contract tests passed, including origin preservation, malformed configuration, forwarding batches, and native node-traffic records.
- Vue/TypeScript checks, changed-file ESLint, PHP formatting, production build, and `git diff --check` passed.
- Local browser checks covered 20 desktop/mobile views and flows, including light/dark dialogs, origin updates, listener disabling, secret masks, forwarding creation, and personal default creation. No browser errors. Browser writes used isolated fixtures and did not reach production.

Live reference reads were reviewed separately. Production saves, purchases, recharges, mapping changes, and provisioning retries were not performed as implementation tests. Existing failed services and unmapped users are not automatically repaired by deploying these changes.

## Deployment

No database migration or SQL update is required for this change.

Deploy the changed `tycdn-backend` application files and the matching `tycdn-backend/public/build` assets together using the existing application deployment process. The archive prepared in the workspace's `.audit/console-validation` folder contains those files with repository-relative paths; it contains no environment file, database, or dependencies. It is an update archive for the existing installation, not a complete installation.

After updating application files, clear cached Laravel routes/views/configuration from the backend directory or the existing app container:

```sh
php artisan optimize:clear
```

Refresh PHP workers through the normal deployment process when opcode caching is enabled. No schema reset or seed operation is needed. For a source build, run `npm run build` before `npm run types:check`: the build regenerates Wayfinder files and must not run concurrently with the type checker.

Verify the deployed admin overview, website editor, group page, finance failure count, settings, and both themes. Use the recovery controls to reconcile the previously reviewed users/service when their current master records have been checked.

## Workflow boundaries

System maintenance displays current versions, status and logs. Upgrade and migration execution links to the existing master maintenance screen. These operations are not duplicated in the console. Some low-level resource bindings retain explicit master-ID inputs. This implementation aligns the console's structure and workflows; it is not a pixel-for-pixel copy of every master screen.

No production deployment was performed from this workspace.
