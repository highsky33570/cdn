# Console USDT display

The console now displays balances, package prices, recharge amounts, traffic-pack prices, discounts, and finance amounts in USDT. Yuan symbols and yuan/USD display labels have been removed from console money fields. The separate CDNfly master panel is unchanged.

Order tables and details use the actual `amount_usdt`, preserving up to six decimal places, including payment-identification suffixes. They no longer display the legacy CNY amount or relabel a fiat quote as USDT. An unavailable amount displays `-`.

The dashboard now unwraps the CDNfly overview response before reading its balance. Package selection now uses the installed checkbox component's `modelValue` binding, restoring individual/all selection and access to batch price editing.

## Accounting and API behavior

CDNfly prices and balance changes continue to use their existing numeric values without conversion. Under this console's accounting convention, a master value of `50` is treated as `50 USDT`; the master panel's fixed yuan symbol does not change the numeric integration.

Payment-gateway currency codes have a different role: they tell the gateway how to quote a payment. Existing product and gateway quote codes, including `USD`, remain unchanged. The public product API was checked read-only on 2026-09-21: its four products all use the existing `USD` code. This update does not convert prices or rewrite stored orders. The exact payable token amount still comes from the gateway.

No SQL, database migration, dependency, or environment changes are required.

## Deploy

`tycdn-usdt-console.zip` contains backend-relative paths. Extract it into the Laravel backend directory containing `artisan`, merging the included `resources/`, `tests/`, and complete `public/build/` files. The archive also includes this guide as `README-USDT.md`.

Deploy the build manifest and its matching assets together. Retain older hashed assets while existing browser tabs may still reference them. Refresh the console after deployment. This package does not contain the public frontend, secrets, or database files. Production deployment has not been performed from this workspace.

## Validation

- 14 JavaScript contract tests passed, including USDT formatting, zero, invalid/missing values, and six-decimal payment precision.
- 23 PHP payment, balance, and settlement tests passed (68 assertions).
- Changed-file ESLint, Vue/TypeScript checks, and the console production build passed.
- Local browser checks cover dashboard balance, account recharge, user package prices, user orders, admin finance details, package batch pricing/selection, and the mobile dark theme. Fixtures deliberately contain a CNY quote different from the actual USDT amount to check that the order display uses the latter. Browser checks do not modify production data.
