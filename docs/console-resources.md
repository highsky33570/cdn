# Resource settings layout

The resource settings page now has Global / Region tabs, with Website / Stream / Common subtabs for global settings. Cards follow the reference layout and field grouping while retaining the project's colors and backgrounds. Controls use the shared Button, Input, Switch, dialogs, and ConsoleDataTable. The layout stacks on smaller screens.

The 21 global settings and six supported regional settings are mapped to the native panel's actual type/name identifiers. Global fields save on change through the existing config upsert API. Values are read from the master; screenshot examples are not used as defaults. The default HTTP port switch sends native string `1`/`0` values. Validation and failed-save retry keep errors visible without claiming success.

Regional settings support creation, editing, selection, pagination, and confirmed deletion. A restricted admin delete endpoint accepts only the six supported regional resource keys and uses the native composite config identity. Deleting an override restores the global setting. Failed bulk deletion retains the remaining records for retry.

Validation: production build, Vue TypeScript, ESLint, PHP formatting, and whitespace checks passed. Eight configuration backend tests passed with 26 assertions. Browser checks covered all four views, correct scope/type/name writes, zero and disabled values, duplicate rejection, all regional field types, failed saves/deletes and retry, light/dark themes, and mobile width. The browser run completed 29 local requests and 24 mocked writes with no page errors. No production configuration was changed.

Deploy `tycdn-resource-settings.zip` into the deployed `tycdn-backend` directory, preserving paths, then run `php artisan optimize:clear`. The package includes cumulative previous console updates and compiled assets. No SQL or migration is required. This package has not been deployed automatically.
