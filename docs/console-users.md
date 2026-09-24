# Users layout

Route: `/console/admin/users`

The main user list now displays native CDN users, matching the reference's user identity/contact/remarks/balance/verification/enabled/type/creation-time columns, bulk-action menu, seven text filters, verification/group selectors and numbered pagination. Balances convert native cents to yuan. Verification reflects secondary authentication when enabled. Tables scroll horizontally without widening the page. Shared console components and theme variables support light/dark mode.

The add/edit dialog has Basic information, Identity information and Login security tabs, with a scrollable body and accessible footer on mobile. Passwords are always blank on edit; saving an unchanged field does not overwrite it. Company fields are available when editing an existing user because the native create endpoint does not persist them. Login verification availability and validation remain governed by the master API, with upstream errors shown in the form. New records are native CDN users; local portal login records are not created or modified by this page's native-account actions.

User groups support create/edit/delete, selection, refresh and pagination. User bulk actions support enable/disable/delete with confirmation and partial-failure retry. Failed IDs remain selected; successful actions are not repeated. Request guards prevent stale lists or details from replacing newer results. Empty states and read failures provide retry controls.

The existing local-account tools remain available through 本地账号管理, including mapping/recovery, package assignment and API-key management. This preserves the distinction between local portal IDs and native CDN user IDs.

## API and user switching

New explicit admin endpoints: `/api/admin/master-users`, `/api/admin/master-users/{id}`, `/api/admin/master-user-groups`, `/api/admin/master-user-groups/{id}` and POST `/api/admin/master-users/{id}/switch`. They use the existing master API service and administrator/verification/rate-limit middleware. Reads allowlist fields and exclude password hashes, API secrets and login tokens. Arbitrary read query parameters cannot trigger token generation. Updates allowlist fields and preserve partial updates; account type is selectable only on creation, as in the reference.

切换 opens the native panel in a separate tab and leaves the Laravel admin session unchanged. A dedicated POST obtains the master's native temporary login token and constructs the native `/dashboard/login` URL. The target uses the master-configured `user_domain`, falling back to the configured master origin when empty. The response is not cached, and the new window has no opener. Disabled accounts and administrators cannot be switched from the UI. No real accounts were switched or modified during validation.

Validation: Vue TypeScript, ESLint, Prettier, PHP Pint, production Vite build; six backend tests with 50 assertions; Chromium fixture checks with 57 requests, 11 mocked mutations and no page errors. Browser coverage includes filters/pagination, cents formatting, authentication-state badges, all editor tabs and switches, partial updates/password omission, read/write errors, stale detail responses, bulk partial failures, groups, native switching, access to local-account tools, and dark/mobile layouts.

## Installation

This ZIP includes cumulative console changes and rebuilt assets. Extract into the Laravel backend directory, replacing matching files, then run:

```sh
php artisan optimize:clear
```

No migration or dependency installation is required. Not deployed by this session.
