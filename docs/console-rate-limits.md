# Console rate-limit fix

The admin group and workspace route previously used overlapping numeric throttles. Laravel gave these the same per-user counter, so a workspace request incremented it twice. Monitoring traffic also consumed the counter used by unrelated write operations.

The new named limits use separate counters:

| Traffic | Default allowance per account |
| --- | --- |
| All admin reads (including monitoring) | 600 requests/minute |
| All admin writes combined | 120 requests/minute |
| Individual write actions | Existing 10/20/30/60 limits, independently per HTTP method and route |

Workspace resource names and CC resource kinds distinguish write actions. Record IDs do not: changing an ID does not reset the quota. Mixed GET/write routes apply their action limiter only to writes. Login limits and administrator authentication/authorization remain in effect.

Framework `Retry-After` and rate-limit headers are now preserved in JSON error responses.

## Apply to the existing installation

Deploy these four files together, preserving the backend-relative paths:

- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `config/rate_limits.php`
- `routes/api.php`

Then run in the backend directory (or the existing application container):

```sh
php artisan optimize:clear
```

Restart persistent PHP workers through the existing deployment process if required by your opcode-cache setup. This update does not require SQL, migrations, dependency installation, or a frontend rebuild.

The defaults work without editing `.env`. Optional overrides are:

```dotenv
ADMIN_API_READS_PER_MINUTE=600
ADMIN_API_WRITES_PER_MINUTE=120
```

If changing these values on an installation that caches configuration, rebuild the configuration cache with `php artisan config:cache`.

## Verification

30 targeted tests passed (699 assertions), covering a 90-request monitoring burst, single counting, independent read/write and account quotas, per-action write caps across different record IDs, mixed GET/write routes, stale configuration-cache defaults, retry headers, existing console workflows, authentication, and upstream error reporting. PHP formatting and diff checks passed.

The update was prepared and tested locally; production deployment was not performed by this workspace.
