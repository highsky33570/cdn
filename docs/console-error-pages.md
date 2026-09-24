# Error pages layout

The admin error-page workspace follows the supplied Global / Region and Node layout: 12 compact template choices, an initially unselected editor, a 380px HTML text area, and a scoped override list. Buttons, tabs, dialogs, table, colors, and backgrounds use the project's components and theme tokens. The controls wrap and stack on mobile.

Global content saves on change, with visible progress, failure messages, and retry. Saves are queued when another edit is in flight. HTML is displayed as text, never executed in the admin console. The WAF template falls back to the existing 403 template only when no WAF template exists. No screenshot content is written to the master on load.

The dedicated admin API uses the master's native `error_page` / `error-page` configuration. Updates fetch the current object and merge edited fields, retaining untouched and unknown fields. Leading/trailing HTML whitespace survives Laravel request normalization. Failed or malformed upstream reads prevent replacement writes. Regional/node overrides support creation, editing multiple supported fields, removal of individual fields, pagination, selection, and confirmed deletion. Duplicate creation and editing missing records are rejected. Removing a field restores inheritance; deleting an override restores inherited pages for its scope.

Validation: production build, Vue TypeScript, ESLint, PHP formatting, whitespace checks, and nine backend tests with 42 assertions. Local browser checks cover all 12 choices, WAF fallback, exact HTML saves, failed-save retry, queued changes, override CRUD, batch-delete retry, pagination, dark theme, and mobile layout. Browser writes were mocked; production settings were not changed.

Extract `tycdn-error-pages.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

This cumulative package includes the previous console updates and current compiled assets. No SQL or migration is required. It has not been deployed automatically.
