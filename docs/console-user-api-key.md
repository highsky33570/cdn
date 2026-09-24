# Customer API key layout

`/console/account/api-key` now matches the supplied flat form: key-status switch, visible `api_key` and `api_secret` values with individual copy buttons, full-width IP whitelist input, and blue reset button. It supports mobile and dark theme layouts.

The dedicated account key-management endpoint remains in use:

- GET loads the saved configuration.
- POST enables API access.
- DELETE disables API access.
- PUT with `reset: true` resets the key pair.
- PUT with `ip` updates or clears the whitelist.

A Save button appears only while the whitelist has edits; Enter also saves. Failed saves retain the draft. Reset and status changes wait until whitelist edits are saved. Controls are disabled during requests. Empty write responses trigger a read of the latest configuration; if that read fails, stale credential values are hidden until a successful retry.

The existing backend continues to synchronize credentials and protect the server integration key. No key values from the reference screenshot are used. Opening the page performs no writes.

Validation: production build, TypeScript, ESLint, formatting, 14 existing API contract tests (54 assertions), and browser checks for copying, whitelist saves/clears, reset, disable/enable, errors/retry, incomplete responses, mobile, and dark theme. Browser credentials were fixtures and all writes were mocked.

## Installation

Extract this cumulative ZIP into `tycdn-backend`, replacing existing files, then run:

```sh
php artisan optimize:clear
```

Previous console updates and rebuilt assets are included. No migration is required. Not deployed automatically.
