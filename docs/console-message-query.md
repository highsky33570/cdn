# Message query layout

The /console/admin/message-query route now uses a dedicated message query page matching the supplied reference and the console's shared theme.

- One toolbar contains the message type selector, inline User ID, User Package ID, and Website ID fields, plus a right-aligned Refresh button.
- The dropdown includes all ten reference categories: package expiry, traffic exhaustion, automatic CC rule switching, bandwidth/connection limits, certificate expiry, and secondary identity verification. Additional types found in returned records remain readable and filterable.
- Table columns: 所属用户ID, 类型, 标题, 用户套餐ID, 网站ID, 创建时间, 操作. The empty state reads 暂无消息.
- Pagination defaults to 10 rows and supports 20/50 rows per page. Filters reset to page one, validate IDs, and apply after a brief typing delay or Enter. Refresh preserves the current filters.
- Details open in a scrollable modal showing title, email content, and SMS content. Content remains escaped text. Loading, retry, empty states, stale-response protection, mobile layouts, and dark mode are supported.

The existing read-only administrator endpoint /api/admin/workspace/message-query is retained. List requests forward type, receive, user_package_id, site_id, page, and limit to the native message API. No backend endpoint changes or production data writes were required.

## Verification

Production build, Vue TypeScript, ESLint, and Prettier passed. Mock browser checks passed: 20 requests, zero writes, zero browser errors. Checks cover all table columns, dropdown options, API filter parameters, pagination, page size, invalid IDs, detail loading/retry, late responses, refresh failure/recovery, empty state, and mobile/dark layouts.

## Installation

tycdn-message-query.zip is cumulative, including the preceding announcements update and rebuilt public/build assets. Extract into the Laravel backend directory while preserving paths, then run:

```sh
php artisan optimize:clear
```

Not deployed by the assistant. Environment secrets, vendor, and node_modules are excluded.
