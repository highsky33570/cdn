# Streams theme and firewall CC integration corrections

## Streams

All three tabs now render their tables through the project's `ConsoleDataTable`. The page retains its existing toolbar, filters, pagination, and management workflows through an optional embedded/controlled mode in the shared table. Existing consumers retain the normal fetching and toolbar behavior. Selection uses the installed checkbox component's `modelValue` events.

Page and dialog buttons use the shared `Button`; status labels use `Badge`. The copied light borders, custom backgrounds, and custom dark palette were removed. Layout-specific CSS uses the project's theme variables, so foregrounds, backgrounds, borders, and controls follow the active theme.

## Firewall

The native CDNfly global CC switch binds booleans. The portal previously submitted numeric `1`/`0`; its own permissive display accepted these, while the master switch compared with boolean values. The portal now submits a boolean and the backend normalizes the global `cc_enable` value before forwarding it. WAF and node/region switches retain their native numeric values. Other configuration fields are preserved.

The regression test runs through the real `CdnflyApiService` with a mocked upstream HTTP server, checks both directions, asserts the transmitted JSON boolean type, and verifies unrelated secrets/templates remain intact. No production CC settings were toggled for validation.

The firewall page retains the requested card/tab layout while using shared buttons, inputs, and switches. Custom colors/backgrounds were replaced by project theme variables.

## Verification

- Production build, Vue TypeScript, ESLint, Prettier, PHP formatting, and whitespace checks.
- 30 targeted backend tests / 225 assertions, including the CC API-service regression.
- Streams browser: 53 local requests / 15 mocked writes; selection, CRUD, batch retry, filters, pagination, dark theme, mobile layout.
- Firewall browser: 37 local requests / 22 mocked writes; boolean CC payload, other switches, autosave/retry, override CRUD, dark theme, mobile layout.
- Existing monitoring page: shared table's normal fetching, search, and client-side pagination, four local requests.

All browser checks completed with zero page errors. Theme checks compare rendered colors with active project theme tokens.

## Deployment

Extract `tycdn-streams-firewall-fix.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The ZIP contains cumulative prior console updates and current compiled assets. No database migration or SQL is required. It has not been deployed automatically.
