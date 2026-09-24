# Admin balance adjustment

Route: `/console/admin/finance/recharge`

Replaces the user-management screen on this route with the reference balance-adjustment form: recharge/deduction selection, searchable user picker, yuan amount, remarks and submit. Shared controls and theme colors support light/dark mode and mobile layouts. The ordinary user-management route retains its existing recharge dialog.

The form uses the existing master-user search picker (ID, email, username or phone). Typing after selecting a user clears the selected ID. Only a selected user can be submitted. Amounts must be positive decimal strings with at most two fractional digits. Successful submissions retain the chosen user and operation, clear amount/remarks and show success. Failed submissions retain the entered values. Pending submissions disable the form and ignore repeated submits.

New admin-only, verified, throttled endpoint: `POST /api/admin/finance/recharge`. It validates `uid`, `type` (`add` or `reduce`), `amount` and optional `des`, then forwards only the native fields to `/v1/user/{uid}/recharge`. User IDs are master IDs from the picker, not local portal IDs. Amounts remain strings; no floating-point rounding is performed. Native business errors are returned as errors through the project's existing error handler.

Validation: four backend feature tests (37 assertions), Vue TypeScript check, ESLint, production Vite build and Chromium checks with mocked API responses. Browser coverage includes user selection, validation, credit/deduction payloads, remarks, disabled controls and duplicate-submit protection, successful reset, error preservation/retry, search failure/empty results, keyboard selection, dark mode and 390px mobile layout. No real balance adjustment was performed.

## Installation

The ZIP is cumulative and includes source plus compiled assets from earlier console updates. Extract into the Laravel backend directory, replacing matching files, then run:

```sh
php artisan optimize:clear
```

No migration or dependency installation is needed. This session has not deployed the update.
