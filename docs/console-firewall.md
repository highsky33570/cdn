# Console firewall configuration

`/console/admin/config/firewall` follows the supplied panel design across three tabs:

- **CC全局配置**: two-column settings cards for protection, blacklist/whitelist durations and thresholds, access controls, default-page protection, cleanup, automatic rule switching, image updates, seven editable challenge templates, diagnostics, and built-in resource protection.
- **WAF全局配置**: the global switch and grouped request-processing limits, explanations, displayed KB/MB units, and recommended defaults.
- **区域及节点配置**: override counts, configuration table, centered create/edit dialog, node/region selection, individual setting selection, and confirmed deletion.

Controls save on change or blur. Internal resource rules use their explicit Save button. Saves are serialized; failures keep edits and offer retry. WAF limits validate ranges and relationships before saving, with the same checks on the server. The shared key is masked. Updates read the current native configuration and merge only submitted fields, preserving unrelated values, templates, secrets, and unknown nested options. Removing an override field restores inheritance for that field; deleting an override restores global configuration for its scope.

The layout supports narrow screens and dark mode. The existing console navigation remains in place.

## API

Administrator-only endpoints:

- `GET/PUT /api/admin/firewall`
- `GET /api/admin/firewall/overrides`
- `PUT/DELETE /api/admin/firewall/overrides/{node|region}/{id}`
- `POST /api/admin/firewall/images`

Global and scoped settings use native `openresty_config/openresty-config` records. Image refresh queues the existing native `download_cc_img` task. Scoped creation rejects duplicates. Global deletion is unavailable. No database schema changes are needed.

## Verification

Production build, Vue TypeScript, lint, and formatting checks passed. Targeted backend suites passed **32 tests / 231 assertions**, covering authorization, key masking, patch preservation, blank resource rows, native boolean handling, validation, scoped creation/edit/deletion, and image refresh. Browser verification completed **37 local requests and 22 mocked writes with zero page errors**, exercising all three tabs, autosave, retries, unit conversion, override workflows, centered dialogs, dark mode, and mobile overflow.

Live master inspection was read-only. No production firewall settings were changed.

## Deployment

Extract `tycdn-firewall-console.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The archive includes this update, earlier console updates, source files, backend routes/controllers, tests, and current compiled assets with their manifest. No SQL or migration is required. The package has not been deployed automatically.
