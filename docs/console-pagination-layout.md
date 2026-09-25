# Unified console table pagination

Every existing user/admin table pagination bar now uses `ConsolePagination.vue`, either directly or through the compatible `PackagePagination.vue` adapter. The shared data-table components and table dialogs use the same footer.

- Total count on the left: `共 N 条`.
- Previous button, plain `第 N 页` label, and next button on the right.
- Outlined 36px buttons with 8px corners, consistent spacing and light/dark theme colors.
- No numbered page buttons or page-size selector. Existing request page sizes remain unchanged.
- Empty and single-page tables retain their footer with unavailable directions disabled. Embedded tables leave pagination to their parent to avoid duplicate controls.
- Existing fetch callbacks, filter resets and loading states remain connected. When an upstream API omits its total, the generic CDN table displays `共 — 条` and uses the returned page length to determine whether it can continue.

The central footer styles live in `resources/css/console-controls.css`. Legacy page-specific footer styles were removed; unrelated toolbar and responsive styles are preserved.

## Validation

Local mocked browser checks cover 22 console views in light/dark/mobile layouts; a populated ACL workflow checks 25 rows across three pages, filtering, reset and empty states. Source-component browser fixtures cover both generic table components, exact single-page boundaries, unknown upstream totals and embedded tables. TypeScript, ESLint/Prettier for changed files, the custom-form audit and the production build were checked. The broader lint/format audit still reports existing issues in untouched files (including StreamDetail.vue); the build emits existing CSS `:deep` warnings. No production data was accessed or changed.

## Installation

The cumulative update ZIP includes previous console layout, form-control and account dark-mode fixes, plus matching production assets. Extract into `tycdn-backend`, preserving environment configuration, then run:

```sh
php artisan optimize:clear
```

This update has not been deployed.
