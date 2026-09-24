# Customer message query layout

`/console/messages` now uses a dedicated page matching the supplied reference:

- Compact message-type, user-ID, user-package-ID, and site-ID filters.
- Columns for recipient, type, title, user package, site, creation time, and Details.
- Chinese notification type labels, with unknown types retained.
- Left-aligned numbered pagination, initially 10 messages per page.
- Full message and SMS content in the detail dialog; the existing mark-read action remains available there.
- Debounced filters, numeric ID validation, empty/loading/error/retry states, and stale-response protection for lists and details.
- Responsive horizontal scrolling and dark theme support.

The page uses the existing customer-credential `/api/cdn/proxy/v1/messages` endpoints. The user-ID filter sends the native `receive` field and does not change the authenticated customer context. Marking a message as read sends the existing POST `/v1/messages/read` request only when explicitly clicked. Message content is rendered as text.

The subscriptions page and admin message-query page retain their existing behavior.

Validation: production build, TypeScript, ESLint, formatting, and local browser checks for all filters, pagination, detail envelopes/content, mark-read success/failure, stale responses, empty/mobile/dark layouts. Browser writes were mocked; no live messages were changed.

## Installation

This cumulative ZIP includes previous console updates and rebuilt assets. Extract into `tycdn-backend`, replacing existing files, then run:

```sh
php artisan optimize:clear
```

No migration is required. Not deployed automatically.
