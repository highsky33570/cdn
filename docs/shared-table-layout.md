# Shared table layout

Both applications import `shared/table-layout.css` first in their global stylesheet. It owns table geometry across all 67 table definitions and 932 header/data-cell templates, including shared datatables, dialogs, loading states, empty states and the public comparison table.

- Header and data cells are 48px high, with no cell padding or vertical borders.
- Each cell contains one `div[data-slot="table-cell-content"]`, with 16px horizontal padding and no vertical padding.
- Horizontal separators use the theme's border color. The inner div reserves one pixel for the separator.
- Long content scrolls inside its cell instead of increasing row height. Existing table-wide horizontal scrolling remains available.
- Calendar days use an accessible grid of divs, preserving their compact controls independently of data tables.

`scripts/check-tables.mjs` checks every Vue template in both applications. It rejects missing content wrappers and cell sizing utilities. Both builds run it through the existing global-style audit. Geometry remains in global CSS; no component style blocks were added.

Date picker triggers, range picker triggers and selected days use the default Button primary/primary-foreground palette. The access-log applied-range button also uses the default variant without a muted text override.

## Validation

Both production builds, Vue TypeScript, changed-component lint, global-style, control and typography audits pass. Local browser checks cover user/admin log queries, orders, shared datatables, long content, date selection and range presets, plus a 32-module light/dark/mobile survey and the public comparison layout. APIs are mocked; no live account changes or deployment were performed.

## Install the cumulative package

Extract the package into the `tycdn-portal` project root. It contains `frontend/`, `tycdn-backend/`, `shared/` and `scripts/`, including fresh assets for both applications. Keep the shared directories alongside both applications and preserve environment configuration.

Then run from `tycdn-backend`:

```sh
php artisan optimize:clear
```

To rebuild later, run `npm run build` from either application's directory.
