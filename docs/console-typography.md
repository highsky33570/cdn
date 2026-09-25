# Shared console typography

Console typography now uses shared roles rather than page-specific font sizes. The scale is defined in `tycdn-backend/resources/css/console-typography.css` and applies to user/admin pages, shared components and portaled dialogs without changing the standalone public frontend.

| Role | Font size | Line height |
| --- | --- | --- |
| Page heading | 20px | 28px |
| Section / card / chart heading | 16px | 24px |
| Dialog heading | 18px | 26px |
| Description | 14px | 22px |
| Body, table cells / column headers, form labels, tabs | 14px | 20px |
| Helper text, captions, code | 12px | 18px |
| Metric value | 24px | 32px |

The same roles use the same sizes at all viewport widths and in both themes. Headings use consistent weights, and the shared Tailwind text scale uses pixel tokens so the console's 14px root font no longer silently shrinks `text-sm` to 12.25px. Existing button/input geometry and pagination remain intact.

Native headings declare `data-typography` explicitly. Existing card/dialog title and description slots use their shared roles automatically. Page-specific CSS font-size declarations reference the shared tokens. The shared Heading component also participates when used inside the console.

`npm run check:typography` audits all 105 console Vue files for heading roles and font-size tokens. Future headings should use `data-typography="page-title"` or `data-typography="section-title"`; page introductions should use `data-typography="description"`, and field guidance should use `data-typography="helper"`.

## Validation

- Typography and custom-form audits, changed-file ESLint/Prettier, TypeScript and production build passed.
- Browser survey of 32 user/admin views in light, dark and mobile layouts, with computed font-size and line-height checks.
- Populated order, cache, certificate and account-log workflows, including dialogs, filters, date selection, submissions and pagination, passed using local mocked APIs.
- The 121 checked screenshots had no page-level horizontal overflow. Scrollable wide tables retain their own horizontal scrolling.
- Existing build warnings about CSS `:deep` remain. No production data changes or deployment were performed.

## Installation

The cumulative ZIP includes previous console updates plus matching production assets. Extract into `tycdn-backend`, preserving environment configuration, then run:

```sh
php artisan optimize:clear
```
