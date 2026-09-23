# Console four-layer forwarding workspace

`/console/admin/streams` now follows the provided panel reference with three tabs:

- **转发列表**: compact toolbar, add forwarding, batch modification, more actions, search type selector, advanced filters, refresh, selection, and pagination. The table shows ID, listening ports, CNAME, origins, package/group, lines, status, and actions.
- **分组管理**: owner-aware group creation, editing, confirmed single/batch deletion, count, empty state, and right-aligned pagination.
- **默认设置**: the bordered forwarding defaults panel with user, setting, value, scope, and actions. Supports listener protocol, load balancing, and Proxy Protocol for a user's global or group scope.

The existing forwarding editor preserves additional listeners, origins, weights, states, and unknown entry fields. It loads complete forwarding details before editing. A new forwarding form reads the selected user's global defaults when the owner changes. Existing bulk creation remains under More Actions; batch modification updates only checked settings. Failed batch changes retain failed IDs for retry. Tabs and pages reject stale read responses. Tables scroll within the page on narrow screens; light and dark appearances are supported.

## API changes

- Administrator-only `GET /api/admin/streams/{id}` forwards to the native stream detail endpoint.
- Administrator-only `GET/POST /api/admin/stream-defaults` and `PUT/DELETE /api/admin/stream-defaults/{id}` use native `/v1/user-configs`, constrained to `type=stream`. Settings validate values and scope; update/delete verifies the record belongs to the stream configuration collection. Group scope verifies ownership.
- Group creation requires an owner ID and validates name/remarks.

## Validation

Production Vite build, Vue TypeScript, ESLint, and Prettier checks passed. Targeted backend suites passed **27 tests / 204 assertions**. Browser verification completed **53 local requests and 15 mocked writes with zero page errors**, covering all tabs, empty and populated tables, filters, pagination, full-detail editing, default/group creation and editing, default application, batch partial failure and retry, deletion, load failure and retry, stale responses, dark mode, and mobile overflow.

Live master checks were read-only. No production forwarding data was changed.

## Deployment

Extract `tycdn-streams-console.zip` into the deployed `tycdn-backend` directory, preserving paths. Then run:

```sh
php artisan optimize:clear
```

The archive contains cumulative prior console updates plus source, backend routes/controllers, tests, and the current compiled assets and manifest. No SQL or database migration is required. The package has not been deployed automatically.
