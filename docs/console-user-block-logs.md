# User block log layout

The user route /console/security/blackip now provides the supplied reference layout through the existing block-log component with an explicit user scope.

- Underlined tabs: 当前拉黑, 拉黑统计, 历史拉黑. Current blocks open by default.
- Current blocks: 解锁IP, 解锁网站, 导出黑名单, followed by IP, website ID, filter ID, and Clear controls. Table includes selection, website ID, domain, IP, location, filter, block time, unlock time, and actions.
- Statistics: rank, website ID, and blacklist count; the native full ranking is paginated locally and opens at 100 rows per page.
- History: export, IP/website/date filters, and website ID, domain, IP, location, filter, block time, and manual-unlock status.
- Left-aligned numbered pagination, horizontal table overflow, compact empty states, shared console colors, and responsive/dark layouts.

User reads and unlock jobs use the existing /api/cdn/proxy endpoints with the signed-in user's CDNfly credentials. Unlocks submit native unlock_ip jobs; the master enforces website ownership. The website unlock modal requires a positive site ID. Access-log links stay in the user portal.

The new GET /api/cdn/block-logs/{resource}/export route streams native text exports for current/history blocks with the signed-in user's credentials. It forwards only approved filters and action=export, excluding pagination, caller-supplied credentials, and ownership overrides. Existing authentication, email verification, and API-key middleware apply. Shared admin behavior remains available under its original default scope.

## Verification

- Production build, Vue TypeScript, ESLint, Prettier, and Pint passed.
- Backend block-log suites: 11 tests, 109 assertions passed, including customer credentials, guarded unlock payloads, filtered exports, invalid ranges, error handling, and existing admin behavior.
- Mock browser checks passed for three tab schemas, filters, pagination, full text downloads, single/bulk/site unlock jobs, date precision, manual unlock labels, retries, access-log navigation, stale responses, empty state, and mobile/dark layouts.
- No live blocks were changed or real unlock jobs submitted.

## Installation

tycdn-user-block-logs.zip is cumulative, including the preceding user sidebar update and current compiled assets. Extract into the Laravel backend directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

Not deployed by the assistant. The archive excludes environment files, vendor, and node_modules.
