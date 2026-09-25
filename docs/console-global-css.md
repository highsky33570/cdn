# Global console styles

All 49 embedded style blocks have been removed from console pages and workspace components. The backend's 274 Vue components now contain no `<style>` blocks. The console uses global CSS imported once by `resources/css/app.css`:

| File | Responsibility |
| --- | --- |
| `console-pages.css` | Page, workspace, chart and dialog layouts, grouped by their source component |
| `console-controls.css` | Shared inputs, action buttons, selection controls, grouped filters and pagination geometry |
| `console-typography.css` | Shared heading, description, table, label and helper text roles |

Page layouts load before shared controls and typography. Vue-only `:deep()` and `:global()` selectors have been converted to ordinary CSS. Page and teleported-content roots carry stable `console-*` namespace classes; the `:is(.namespace, .namespace *)` selector matches the root and its descendants with consistent specificity. Keep the namespace when adding a dialog or popover so its layout works outside the page DOM.

Action-button rules target the shared Button's `data-slot="button"` and `console-*` slots. They must not reach the internal buttons used by selects, calendars, switches or checkboxes. Common dimensions and typography belong in the shared files, not additional per-page overrides. Dynamic inline styles for chart positions and other runtime values are unchanged.

`npm run check:styles` rejects embedded Vue styles and Vue-only selectors in CSS. `npm run check:typography` now audits the global page stylesheet as well as template text roles.

## Validation

Production build, TypeScript, changed-file ESLint/Prettier, global-style, shared-form and typography checks pass. Browser validation uses mocked APIs and checks light/dark/mobile views plus populated workflows for orders, logs, certificates, cache jobs, sites, streams, ACL/CC rules, messages, subscriptions and API keys. No production writes were made.

## Installation

The cumulative ZIP includes prior console updates, the global styles and matching production assets. Extract it into `tycdn-backend`, preserving environment configuration, then run:

```sh
php artisan optimize:clear
```

The subsequent repository-wide migration also covers the public frontend. See [global-css.md](global-css.md) for the current stylesheet layout and installation instructions. Deployment has not been performed.
