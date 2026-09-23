# Quality monitoring chart fix

Applies to `/console/admin/analytics/realtime` and the shared personal realtime page.

The live master and console APIs both returned populated HTTP 400 and 530 series. CDNfly encodes these as `{ data: [{ name: "400", value: [[timestamp, count], ...] }] }`. The old parser only understood plain timestamp tuples or timestamped objects and discarded the named series.

Changes:

- Parse native named status-code series, preserving individual codes and actual samples.
- Keep a status-code legend even when only one code is present; include the code in tooltips.
- Preserve native cache-hit percentages below 1%; missing samples remain missing.
- Share the Chart.js loading promise across concurrent metrics to avoid replacing chart instance registries.
- Remove the tab strip's overflow and negative button margin; tabs wrap at narrow widths.
- Allow chart grid cards to shrink so mobile charts remain inside the screen.

Validation: seven parser regression tests, TypeScript, ESLint, production build, and browser checks using captured live responses. Browser coverage includes both themes, mobile layout, single/multiple codes, filters, empty/error states, and tab switching. Live access was read-only; browser fixtures are not included in production code.

## Installation

Extract `tycdn-quality-monitoring-console.zip` into the existing `tycdn-backend` directory. Upload the complete included `public/build` directory, including its manifest and hashed assets, together. The source files and regression tests are included for future rebuilds.

This is a frontend patch for the current console version. No SQL, database migration, API policy change, or master-panel change is required. It has not been deployed automatically.
