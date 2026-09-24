# Default settings layout

The admin defaults page now uses Global / Region tabs and Website / Stream / Certificate subtabs. The website tab follows the supplied card layout for HTTP, HTTPS/SSL, origin settings, cache, request headers, access logs, security, and other defaults. Controls, dialogs, colors, backgrounds, and the regional table use project components and theme tokens. Cards stack on small screens.

Field identities and SSL presets were checked against the native master panel code. Saves retain the native `site_default_config`, `stream_default_config`, and `cert_default_config` categories. Switches use string `1`/`0`, SSL protocol lists use space-separated strings, and structured defaults use JSON. WAF auto-block settings keep numeric members. Existing unknown object members and cache/header properties survive editing. Loading does not save screenshot examples or inferred values to the master.

Global fields save on change with visible errors and retry. SSL preset saves report failures for each affected setting. Cache/header editors support creation, editing, removal, and cache ordering; quick cache selections open a draft for review. Regional settings use the shared table and support creation, editing, selection, pagination, and confirmed deletion. The new restricted delete endpoint only accepts supported regional default settings. Removing an override restores the global default.

Validation includes the production build, Vue TypeScript, ESLint, PHP formatting, whitespace checks, and 10 configuration backend tests with 33 assertions. Local browser checks cover native payloads, all tabs, SSL preset retry, cache/header edits, preservation of unknown data, malformed structured values, WAF changes, regional CRUD, errors/retry, dark theme, and mobile layout. Browser API writes were mocked; production settings were not changed.

Extract `tycdn-default-settings.zip` into the deployed `tycdn-backend` directory, preserving paths, then run:

```sh
php artisan optimize:clear
```

The package contains cumulative prior console changes and current compiled assets. No SQL or migration is required. It has not been deployed automatically.
