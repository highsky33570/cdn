# Site analysis tables and access-log links

The analysis page now shares six columns across all eight tabs: rank, the tab's dimension, requests, outbound traffic, origin traffic, and actions. Tables use the available panel width, preserve horizontal scrolling on small screens, and offer sorting on all three numeric columns. Traffic uses the master's decimal units and displays a real zero as `0 Bytes`.

Every View Logs action opens `/console/admin/analytics/logs` with the matching dimension selected and applied automatically. Domain and URL rows include the listening port; URL rows preserve the complete exact URI, including nested paths and encoded query strings. TLS fingerprint, IP, country, province, ISP, and referer rows each use their corresponding access-log filter. Links preserve the loaded ranking time window and domain/port context.

The access-log page now initializes its selected search field, search value, and time range from these links. Rapid ranking tab changes cannot replace the current tab's data with a stale response. Custom ranges longer than the master's one-hour limit are rejected locally with an explanatory message. Failed queries clear stale rows.

Validation used the live master's analysis bundle and real responses from both master and console for all eight dimensions (2026-09-23 21:00–22:00). Seven regression tests, TypeScript, ESLint, production build, and browser checks passed. Browser checks exercised all eight links through the resulting access-log API request, numeric sorting, filters, empty/error states, rapid tab changes, and light/dark/mobile layouts. No production data was changed.

## Installation

Extract `tycdn-site-analysis-console.zip` into the existing `tycdn-backend` directory. Upload the complete included `public/build` directory with its manifest and hashed assets together. Updated Vue, TypeScript, CSS sources and regression tests are included for future builds.

No SQL, database migration, or master-panel change is needed. The update has not been deployed automatically.
