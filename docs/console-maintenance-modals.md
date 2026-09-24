# Maintenance page modal actions

The maintenance page no longer displays the master upgrade and migration log sections in its main layout. The top-right actions now include 查看日志 and 升级主控, alongside Refresh.

查看日志 opens a scrollable modal with a log-type selector and refresh action. Upgrade and migration logs load when requested and retain their loading, empty, and error states. Log content is rendered as text. Request tracking prevents older responses from replacing newer results.

升级主控 opens a version-selection modal and refreshes the current master version information. Opening it does not start an upgrade. The user must select an available newer version and press 确认升级. Confirmation is disabled while loading, while an upgrade is running, when no newer version is available, or while a request is pending. Failed requests leave the selection available for retry; successful requests open the upgrade logs.

The admin workspace endpoint validates an explicit start action and integer target version. It forwards only these fields as query parameters to the native POST /v1/master/upgrades endpoint. Existing administrator authorization remains in place; the native master validates available versions.

## Verification

- Production build, Vue TypeScript, ESLint, Prettier, and PHP Pint checks passed.
- Maintenance feature suite: 11 tests, 77 assertions passed.
- Browser checks passed for log selection, refresh, empty/error states, version selection, failed and successful mocked upgrade requests, duplicate-submit protection, and mobile/dark layouts. No browser errors were recorded.
- No live master upgrade or production deployment was performed.

## Installation

The cumulative tycdn-maintenance.zip includes the preceding console updates, current source changes, and rebuilt public/build assets. Extract it into the Laravel backend directory, preserving the directory structure, then run:

```sh
php artisan optimize:clear
```

The archive does not contain environment secrets, vendor, or node_modules.
