# Global CSS across both applications

Both applications now keep CSS outside Vue components. The first migration covered the console; this update also moves the remaining 24 frontend component style blocks into global CSS. All 300 application Vue files (26 frontend, 274 console/backend) contain no embedded styles.

## Stylesheet ownership

The frontend imports `src/styles/global.css` once from `src/main.js`. It loads:

- `shell.css`: header, footer and theme toggle.
- `landing.css`: homepage sections, including `AdvantagesSection.vue`, and network illustrations.
- `catalog.css`: product listings, plan cards and details.
- `auth.css`: login, registration, password recovery, email verification and two-factor challenge.

Shared frontend palette, typography and controls remain in `global.css`. The console continues to use `resources/css/app.css`, `console-pages.css`, `console-controls.css` and `console-typography.css`.

Frontend roots carry stable `public-*` classes and `data-public-style`. Global selectors preserve their specificity while excluding nested component internals, so a detail page's list rules cannot change its plan card or footer. Keep these hooks when modifying root markup. Vue-only selectors have been removed, and animation names are namespaced. Runtime styles used for chart positions or animation delays remain dynamic.

## Enforcement and validation

Run `npm run check:styles` from either application. The shared `scripts/check-global-styles.mjs` checks both applications, and both production builds run the audit automatically through `prebuild`. It rejects embedded Vue styles, including scoped blocks, and Vue-only CSS selectors. Both frontend and backend rejection paths were verified.

The frontend production build passes. Before/after browser comparison covered nine routes in light/dark themes at desktop/mobile widths: 36 layouts and 4,478 visible elements, with no differences in the measured geometry, typography, colors or spacing. Interaction checks use mocked APIs; no live account operations were performed.

Files under the workspace's `.audit` directory are historical test fixtures and snapshots, not application source or build inputs. They are excluded from the deployment package and this application-style audit.

## Installation

The cumulative ZIP now has project-root paths, including `frontend/`, `tycdn-backend/` and `scripts/`. Extract it into the **`tycdn-portal` project root**, not directly into `tycdn-backend`. It includes the preceding console update and rebuilt frontend assets. Preserve environment configuration.

Then run from `tycdn-backend`:

```sh
php artisan optimize:clear
```

To rebuild either application later, run `npm run build` in that application's directory. The shared root `scripts/` directory must remain alongside both applications. No deployment has been performed by this update.
