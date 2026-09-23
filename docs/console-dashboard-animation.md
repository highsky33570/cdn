# Operations chart animation

The registration, package sales, and recharge bars on `/console/admin` grow from their baseline over 700 ms, with a short stagger between bars. The animation plays after data loads and after a date range change. Values, axes, and tooltips retain the actual API data. Reduced-motion preferences show the completed bars immediately.

Validated in the browser for growth direction, replay, both themes, keyboard tooltips, and reduced motion. ESLint and the production build passed.

Extract `tycdn-dashboard-animation-console.zip` into the existing `tycdn-backend` directory. Upload the complete included `public/build`, including the manifest and all hashed assets, together. The updated `DashboardChart.vue` source is also included. No SQL or database migration is required. This package has not been deployed automatically.
