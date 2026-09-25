# Console form and pagination consistency

The ACL name and ID filters now render as a single bordered control. The old layout combined a 40px wrapper with a separately bordered, 32px shared input. Console-wide styling also mixed 28px, 32px, 36px and 40px controls and page-specific pagination rules.

`resources/css/console-controls.css` is now the shared console geometry contract, imported by `app.css`. It covers the console and its portaled dialogs without changing public/auth page styles.

| Control | Shared presentation |
| --- | --- |
| Inputs, selects, date triggers, action buttons | 40px height, 14px text, 20px line height, 4px corners |
| Prefix/suffix/search groups | One 40px border; flush inner fields; one focus outline |
| Pagination | Total on the left; 36px previous/next buttons and plain current-page label on the right; see `console-pagination-layout.md` |
| Shared icon buttons | 32px square |
| Checkboxes and radio buttons | 18px; shared checked colors and shapes |
| Switches | 40 × 22px with an 18px thumb |
| Textareas | Shared typography and corners; retain their multiline height |

Existing shared Input, SelectField, DatePicker, DateRangePicker, Checkbox, RadioGroup, Switch and Button components remain in use. Legacy grouped fields and action buttons explicitly opt into the shared styles using `data-slot`; table footers use ConsolePagination. Widths, table scrolling and responsive wrapping remain local. Calendar day cells, tabs and menu items retain their separate presentation.

The central geometry rules deliberately take precedence over legacy scoped page sizes. New console pages should use the shared components and slots rather than add page-specific control heights. Selection controls also override broad legacy `button` rules so checked colors, circles and switch thumbs do not inherit action-button padding or borders.

Validation uses local mocked APIs only: 13 customer/dialog workflow suites, a dimension survey across 15 admin/analytics views in light/dark/mobile layouts, the native-control audit across 173 Vue views/components, TypeScript, ESLint, Prettier and a production Vite build. Browser checks include nested borders, alignment, collapsed search inputs, selection-control geometry and pagination. No production requests, data changes or deployment were performed.

## Installation

The accompanying ZIP is cumulative and includes the earlier account dark-mode changes. Extract its contents into `tycdn-backend`, keeping existing environment configuration, then run:

```sh
php artisan optimize:clear
```

The ZIP includes the matching production `public/build` assets and manifest.
