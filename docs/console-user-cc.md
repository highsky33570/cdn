# User CC rules layout

Updates `/console/security/cc` to the supplied CC rule screenshots.

- Three bordered tabs: 规则组, 匹配器, 过滤器.
- Compact add / more-actions toolbar and inline name, ID, status and rule-group visibility filters, with debounced search and clear.
- Reference table columns, 48 px headers, 60 px rows, compact empty states, selection, and left-aligned numbered pagination (10 rows by default).
- Existing add/edit dialogs remain connected. Supports customer rule, matcher and filter enable/disable/delete actions, deletion confirmation and partial-failure selection retention.
- Customer requests stay on the existing authenticated user APIs, including `internal_self=1` to display available system templates. System templates are read-only. The screenshot's cross-user ID filter is omitted on this customer-scoped page.
- Mobile horizontal table scrolling, keyboard tab navigation and dark theme.

Validation: production build, Vue/TypeScript checks, ESLint, existing CC helper tests, and local browser checks of real compiled assets. Browser checks cover all three tables, filters, pagination, editors, batch operations, partial failures, deletion, stale responses and mobile/dark layouts. No production data was changed.

## Apply

The ZIP includes all previous cumulative updates and compiled frontend assets. Extract into `tycdn-backend`, replacing matching files, then run:

```sh
php artisan optimize:clear
```

No database migration is required. This update has not been deployed by the assistant.
