# User websites layout

Updates `/console/sites` to the supplied user portal reference, with five underlined tabs: 网站列表, 分组管理, 默认设置, DNS API and 解析检测. The site list now has the reference column order, a horizontally scrolling table, compact statuses, row actions, selection and left-aligned numbered pagination. Existing create/edit dialogs are retained.

The toolbar provides domain/ID/CNAME search, advanced filters, CSV export of all matching sites, batch group/package updates, certificate application, enable/disable, delete, cache clearing and blacklist unlocking. Batch confirmations identify the selected records; failed items remain selected for retry. Existing disabled-site deletion requirements are preserved. Cache and unlock job payloads follow the native panel contract. All operations use existing authenticated customer endpoints.

Groups, site defaults and DNS APIs use compact tables with selection and pagination. DNS checks include API configuration and task-state filters, domain/site filters, CNAME result icons and selected-domain synchronization. Direct group/DNS routes load the appropriate section.

Validation: frontend production build, Vue/TypeScript and ESLint passed. Local mocked browser checks covered the five tabs, filters and pagination, complete CSV export, partial batch failures, native cache/unlock jobs, certificate creation/binding, deletion, group creation, existing dialogs, DNS checks/sync, direct group routes and mobile/dark layouts. No production mutation or deployment was performed.

## Install

This cumulative archive includes prior console updates and compiled frontend assets. Back up the existing installation, extract into `tycdn-backend`, then run:

```sh
php artisan optimize:clear
```

Do not extract into the public directory. No database migration is required.
