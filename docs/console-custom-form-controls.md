# Console form component audit

The project contains a Laravel API/backend, a Vue/Inertia console under `tycdn-backend/resources/js`, and a separate Vue frontend under `frontend/src`. The console uses its existing Reka-based UI components; the separate frontend already uses Arco Design form components.

The audit found and migrated **317 controls across 62 files** in the administrator and customer console. This includes shared editors, filters, pagination, dialogs, account settings, site and forwarding management, certificates, security, cache jobs, monitoring, orders, and messages.

| Control | Shared implementation |
| --- | --- |
| Text, password, numeric fields | Existing `ui/input/Input.vue`; preserves `.trim`, `.number`, `.lazy` and native input/change events |
| Dropdowns | `ui/select/SelectField.vue` and `SelectOption.vue`, composed from existing Select primitives |
| Checkboxes | `ui/checkbox/CheckboxField.vue`, composed from existing Checkbox; supports boolean/array models, checked bindings, disabled and indeterminate states |
| Switches | Existing `ui/switch/Switch.vue` |
| Radio groups | `ui/radio-group/RadioGroup.vue` and `RadioGroupItem.vue`, using the same Reka toolkit |
| Multiline fields | `ui/textarea/Textarea.vue` |
| Single dates/date-times | `ui/date-picker/DatePicker.vue`, using a shared Reka Calendar, existing Button/Input/Select components and a custom popup |
| Date ranges | Existing `ui/date-range-picker/DateRangePicker.vue`; now synchronizes external resets and provides keyboard-focusable day buttons |

Dropdown adapters retain empty filter options, numeric option values, existing change handlers, and initial matching of numeric API values against string options. Date-time fields retain local date/time strings and optional seconds without timezone conversion. Required select/date fields retain form validation and serialization through visually hidden inputs.

Native text elements exist only inside shared primitives; two-factor forms retain their hidden code fields. There are no page-level visible native inputs, textareas, selects, radios, checkboxes, switches, or browser date pickers. Existing backend endpoints and authorization are unchanged.

## Verification

- `npm run check:forms`: scans 173 application views/components across both Vue applications, rejects visible native controls outside UI primitives and browser date/radio/checkbox widgets passed to Input.
- Production build, Vue type checking, lint and formatting checks on changed sources.
- `npm run test:contracts`: 32 contract tests.
- Local browser checks with mocked APIs: sites, certificates, cache, ACL/CC rules, streams, orders, account logs, messages, subscriptions, API keys, administrator node editing and default settings.
- Shared-component browser checks cover input modifiers, empty/numeric dropdown values, checkbox values and arrays, radio keyboard navigation, switches, calendar/time selection including seconds, clearing, required validation, and disabled fieldsets.
- Customer page checks include mobile/dark layouts and failed-save rollback. All mutations in these checks use local fixtures.

Reka references: [Calendar](https://reka-ui.com/docs/components/calendar), [Radio Group](https://reka-ui.com/docs/components/radio-group).

## Install the cumulative update

Extract the update ZIP into `tycdn-backend`, then run `php artisan optimize:clear`. The ZIP includes compiled assets. For a source rebuild, run `npm ci`, `npm run check:forms`, `npm run build`, then `npm run types:check` (after the build's route generation completes). No database migration is required. This update has not been deployed by the agent.
