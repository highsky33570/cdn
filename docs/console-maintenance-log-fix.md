# Maintenance log availability fix

Read-only checks against the live master on September 21, 2026 confirmed:

- `/v1/master/transfer-status` returns `false`.
- `/v1/master/transfer-log` returns code `system-30` because `/tmp/master_transfer.log` does not exist.
- `/v1/master/upgrades` returns `upgrade_run: false`.
- `/v1/master/upgrades/log` returns code `upgrade-12` with `upgrade处理失败: exit status 1`.

The console previously treated both log responses as generic server failures.

## Behavior

Only the exact missing-transfer-log response becomes a successful response with `available: false` and an explanation that the temporary log has not been generated or has been cleared.

The exact upgrade-log read error becomes an unavailable-log response only after another master request confirms that no upgrade is running. Exit status 1 is not assumed to prove a missing file. The explanation retains the read command's exit status. An active upgrade, an unknown upgrade state, or a failed status lookup preserves the original log error.

Permission errors, authentication failures, HTTP failures, other resource errors, and non-GET requests retain their existing error handling. Existing log content passes through unchanged.

The maintenance page displays unavailable and empty logs clearly, renders existing logs as multiline text, and updates correctly when refreshed. These changes affect log viewing; no upgrade or migration was started.

## Deployment

Extract `tycdn-maintenance-log-fix.zip` into the deployed Laravel backend directory containing `artisan`, preserving archive paths. It contains:

- `app/Services/CdnflyApiService.php`
- `resources/js/pages/console/AdminMaintenance.vue`
- `tests/Feature/AdminMaintenanceLogTest.php`
- The complete rebuilt `public/build` assets and manifest
- These deployment notes

Deploy the PHP file and build assets together. Retain older hashed assets while existing browser tabs may still reference them. Then run in the backend directory or application container:

```sh
php artisan optimize:clear
```

Reload persistent PHP workers if applicable to the existing deployment, then refresh the console. No SQL, migrations, dependency installation, or server-side frontend build is required.

## Verification

25 targeted PHP tests passed (126 assertions), including log availability, active and unknown upgrade states, failed status lookups, preservation of real errors and log contents, administrator access, and the previous date-format fix. PHP formatting, frontend lint, Vue/TypeScript checks, and the production build passed.

Local browser checks using rebuilt assets and fixture API responses passed for unavailable, populated, failed, and empty logs, including a mobile viewport. No browser exceptions or write requests occurred. Live API checks were limited to reading master status and log responses.

The patch is prepared locally; production deployment has not been performed from this workspace.
