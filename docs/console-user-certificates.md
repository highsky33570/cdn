# User certificate page

Updates `/console/certificates` to the supplied three-tab reference: 证书列表, 默认设置 and DNS API. The certificate list has the reference toolbar, domain/name/ID search, advanced filters, selection, reissue and renewal actions, row menus, a wide scrolling table and left-aligned numbered pagination. Existing certificate upload/application and edit dialogs are retained.

Default provider and DNS API changes save through the existing customer `user-configs` API using `type=cert`, global scope, and the native `cert_default_type` / `dnsapi` setting names. Returning to system defaults or clearing DNS removes the corresponding override. Failed saves restore the previous displayed value. DNS API management reuses the user websites editor in an embedded layout.

All operations use authenticated customer APIs. Failed batch items remain selected for retry. No backend migration or configuration change is required.

Validation: production build, Vue/TypeScript and ESLint passed. Local mocked browser checks covered the three tabs, search/pagination, reissue and renewal, partial failures, create/edit dialogs, settings create/update/delete and rollback, DNS management/deletion, mobile/dark layout, and preservation of the standalone websites tabs. No production writes or deployment were performed.

## Install

This cumulative archive includes the preceding console updates and compiled frontend assets. Back up your installation, extract into `tycdn-backend`, then run:

```sh
php artisan optimize:clear
```

Do not extract into the public directory. No frontend build is needed with the included assets.
