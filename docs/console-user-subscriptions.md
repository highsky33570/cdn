# Message subscription table

`/console/messages/subscriptions` now matches the supplied three-column layout: message type, phone reminder, and email reminder. The six standard types appear in the reference order, with Chinese labels, 48px table headers, 60px rows, and blue checkboxes.

The table reads saved subscriptions from the existing customer API. It does not assume that all checkboxes are enabled or create missing records. Additional returned types remain visible. Loading, empty, failed-load/retry, mobile, and dark theme states are supported.

Clicking a checkbox saves through the existing PUT `/v1/messages/sub` request, preserving the other notification channel. Both controls in that row are disabled while saving to avoid conflicting writes. A failed save restores the previous value and displays a retry action beside that row. No changes are submitted when the page loads.

The obsolete messages list/form code in `UserMessages.vue` has been removed; `/console/messages` continues to use its dedicated `UserMessageQuery.vue` page.

Validation: production build, TypeScript, ESLint, formatting, and local browser checks for saved boolean/numeric states, independent channel updates, row locking, failure rollback/retry, persistence, load recovery, unknown/empty responses, mobile, and dark theme. All browser writes were mocked; no live subscription settings were changed.

## Installation

This cumulative ZIP includes prior console updates and rebuilt production assets. Extract into `tycdn-backend`, replacing existing files, then run:

```sh
php artisan optimize:clear
```

No migration is required. Not deployed automatically.
