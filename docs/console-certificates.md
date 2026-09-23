# Admin certificate workspace

`/console/admin/certificates` now follows the supplied panel screenshots with three tabs: 证书列表, 默认设置 and DNS API.

- The compact certificate list shows ID, owner, certificate name/domain with copy buttons, certificate type, creation/expiry dates, remaining days, renewal status, issuance/synchronization status and actions. Search, advanced filters, current-page summary counts, CSV export and 10-row pagination follow the reference layout.
- Management supports metadata changes and explicit certificate/private-key replacement. Existing PEM contents are not returned by the new detail endpoint. Automatic certificate requests include the selected owner and that owner's DNS API. Row and selection actions support reissue, enable/disable, renewal and confirmed deletion; failed items remain available for retry.
- Defaults start with the requested user picker and empty-state hints. Selecting a user loads that user's certificate type and DNS API defaults. Changes save automatically using native `user-configs` records with `type=cert` and global scope. Resetting a default deletes its override. DNS credentials are omitted, and another user's DNS API cannot be assigned through the defaults endpoint.
- The DNS API tab reuses the site workspace's provider forms and owner/name/type/remark table, including credential replacement and deletion behavior.
- Dialogs are centered. Light, dark, narrow-screen, empty, loading and failed-request states are supported. Delayed user requests cannot replace a newer selection.

Native panel code and read-only certificate/user/default responses were checked for field names, filters and default-setting payloads. Writes were tested locally using mocks; production certificate and DNS records were not changed.

## Validation

Production build, Vue type checking, ESLint and formatting; nine certificate/site backend tests (55 assertions); three certificate-status/expiry JavaScript tests; browser checks for all three tabs, filters, export, metadata and PEM editing, reissue, selected-owner creation, default saves and failures, switching users, shared DNS forms, deletion retry and desktop/mobile themes. The existing five-tab site workspace browser regression also passes.

## Applying the package

Extract the archive into `tycdn-backend`, preserving paths. Upload the included source files and the complete `public/build` directory together, run `php artisan optimize:clear`, then hard-refresh the browser. The new certificate controller and updated API routes are required alongside the frontend assets.

The archive includes earlier console updates. No SQL or migration is required. It is not deployed automatically.
