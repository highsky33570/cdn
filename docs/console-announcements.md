# Announcement management layout

The admin workspace messages route now uses a dedicated announcement page matching the supplied reference, with the console's shared colors, buttons, tables, and dialogs.

- Left toolbar: 新增公告 and 刷新. Right toolbar: 显示, 隐藏, 删除, enabled when rows are selected.
- Selectable, horizontally scrollable table: ID, 标题, 跳转, 弹窗, 样式, 显示, 排序, 添加时间, 更新时间, 操作. Includes empty/loading/error states and numbered pagination with 10/20/50 rows per page.
- Add/edit modal: 基础信息 and 展示状态 sections; title, content or redirect link, sort order, visibility, popup, red and bold options. Defaults match the supplied reference.
- Editing loads the full announcement record, including content omitted by the list endpoint. Late detail responses cannot overwrite a newly opened form.
- HTML remains editable text in this admin interface. Title cells render escaped text.
- Save failures preserve the form. Duplicate saves are blocked while pending. Deletion requires confirmation, and partially failed bulk operations retain failed IDs for retry.

The page uses the existing administrator workspace API at /api/admin/workspace/messages, filtering lists with type=announcement. Existing native POST, item PUT, item DELETE, and detail GET contracts are retained. Visibility actions send only is_show; content and redirect forms send their active content field. No backend endpoint changes are required.

## Validation

Production build, Vue TypeScript, ESLint, and Prettier passed. Browser checks passed with mocked API responses: 24 requests, 8 mock writes, no browser errors. Coverage includes pagination, form validation/defaults, create/edit payloads, display and style controls, content/link switching, failed saves, detail retry, stale responses, visibility actions, partial deletion retry, loading errors, empty state, and mobile/dark layouts.

No production announcements were created, edited, shown, hidden, or deleted during verification.

## Installation

tycdn-announcements.zip is cumulative, including the preceding maintenance update and current compiled assets. Extract into the Laravel backend directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The update has not been deployed. The archive excludes environment files, vendor, and node_modules.
