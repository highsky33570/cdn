# Console Nginx configuration

`/console/admin/config/nginx` follows the supplied panel screenshots:

- **全局配置 / Worker**: process and connection settings, file limits, shutdown timeout, and log directory in two cards.
- **全局配置 / Http**: Gzip, origin/cache, and connection/response cards with switches, HTTP-version buttons, and the client-body buffer's KB label.
- **全局配置 / Stream**: connection and forwarding timeouts in one card.
- **区域及节点配置**: compact scope/settings table, empty state, create/edit dialogs centered in the viewport, individual override selection, pagination, and confirmed single/batch deletion.

The page supports light/dark appearances and narrow screens. Values load from the existing native configuration; screenshot values are not substituted for real settings. Controls save on change or blur. Saves are serialized, and failed writes retain edits with retry. Only changed leaf fields are sent, so HTTP and Stream settings with identical directive names remain independent. Updates read the current native configuration before merging and preserve unrelated settings and unknown options.

Native switches preserve `on`/`off` and `1`/`0` strings. The client-body buffer value remains in the panel's native KB representation. `server_tokens` is treated as an ordinary directive, while unknown credential fields remain masked. Basic validation covers required values, numeric limits, version choices, and switch values. Validation of a complete generated Nginx configuration remains the upstream system's responsibility.

## API

Administrator-only endpoints:

- `GET/PUT /api/admin/nginx`
- `GET /api/admin/nginx/overrides`
- `PUT/DELETE /api/admin/nginx/overrides/{node|region}/{id}`

These use native `nginx_config/nginx-config-file` records. The global autosave route allows up to 60 writes per minute within the existing overall admin rate limit. Scoped creation rejects duplicate records, editing a removed record returns an error, and removing selected overrides preserves other fields. Global deletion is unavailable.

## Verification

Production Vite build, Vue TypeScript, ESLint, Prettier, and PHP formatting checks passed. Targeted backend suites passed **33 tests / 155 assertions**, including nine Nginx tests for authorization, native field mapping, preservation, masking, validation, upstream failure, and scoped changes. Browser verification completed **40 local requests and 20 mocked writes with zero page errors**, covering all views, failed saves and reads, queued autosaves, independent HTTP/Stream values, overrides, partial deletion failure, pagination, centered dialogs, dark appearance, and mobile overflow.

Reference field syntax was checked against the native panel and the [Nginx Gzip documentation](https://nginx.org/en/docs/http/ngx_http_gzip_module.html#gzip_comp_level). Time and size values retain native text notation described by the [Nginx measurement units reference](https://nginx.org/en/docs/syntax.html).

Live inspection was read-only. No production Nginx configuration was changed.

## Deployment

Extract `tycdn-nginx-console.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The archive includes this update, prior console updates, source files, backend controllers/routes, tests, and current compiled assets with their manifest. No SQL or migration is required. The package has not been deployed automatically.
