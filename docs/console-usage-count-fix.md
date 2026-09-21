# Console usage-count date fix

The overview sent full timestamps to `/v1/monitor/usage-count`. The live master rejected the reported request with `monitor-usage-count-7` / `开始时间日期格式不正确`. Read-only checks confirmed that `YYYY-MM-DD` boundaries return code `0` and the four usage metrics. The master dashboard uses calendar-day boundaries with an exclusive end date.

## Changes

- The overview requests calendar-day totals and labels the choices `今日` and `近 7 天（含今日）`. For September 21, these send September 21–22 and September 15–22 respectively; the end date is excluded.
- The backend accepts existing date-only requests and older timestamp requests. It rounds the start down to its calendar date and a partial end day up to the next date. An already-exclusive midnight or date-only end is preserved. The reported September 20–21 timestamp range therefore becomes September 20–22.
- Invalid dates and reversed or empty ranges return a validation response without calling the master. Realtime chart requests retain their timestamps.

## Deployment

Extract `tycdn-usage-count-fix.zip` into the existing backend directory, preserving the archive paths. The archive includes the updated controller, frontend source, tests, and the complete rebuilt `public/build` assets and manifest. Publish the PHP change and build assets together using the existing deployment process. Keep existing hashed assets while old browser tabs may still reference them.

Run in the backend directory or application container:

```sh
php artisan optimize:clear
```

Reload persistent PHP workers if the deployment uses them, then refresh the console. No SQL, migrations, dependency installation, or server-side frontend build is required.

## Verification

- Live master: the original timestamp format failed; two date-only requests succeeded.
- 23 targeted PHP tests passed with 689 assertions, covering usage-count, the rate-limit policy, and existing console workflows.
- All 13 JavaScript contract tests passed; frontend lint, Vue/TypeScript checks, PHP formatting, and the production build passed.
- Local browser checks using the rebuilt assets verified today's and seven-day requests, rendering of returned totals including zero, and a mobile viewport without horizontal overflow. No browser exceptions occurred. Browser API responses were fixtures; the date-format check above used the live master.

The patch is prepared locally. Production deployment has not been performed from this workspace.
