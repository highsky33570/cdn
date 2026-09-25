# Console CSS build warning fix

The supplied production log shows a successful build. Its LightningCSS warnings were caused by Vue-only `:deep()` syntax surviving into production CSS.

Removed `:deep()` wrappers from the unscoped Firewall and Nginx styles, and from inside `:global()` selectors in the WAF and Stream workspace styles. These selectors now use valid CSS while retaining their page/dialog prefixes. Valid scoped `:deep()` selectors remain unchanged.

This cumulative package includes the preceding console layout, shared controls, pagination and typography updates, plus matching rebuilt production assets. It supersedes the earlier typography package's note about remaining CSS warnings.

## Validation

- Production build passes without LightningCSS selector warnings; all 45 generated CSS files contain no Vue-only selectors. Vite still reports an informational plugin timing warning.
- Compiled-style audit across all Vue components passes.
- Changed-file formatting and ESLint, shared-form and typography audits pass.
- Four affected pages pass local browser checks in light, dark and mobile layouts, including shared control geometry, typography and pagination. Browser APIs were mocked; no live writes were made.

## Installation

Extract the ZIP into `tycdn-backend`, preserving environment configuration, then run:

```sh
php artisan optimize:clear
```

No production data changes or deployment were performed locally.
