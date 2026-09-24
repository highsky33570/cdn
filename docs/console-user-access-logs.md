# User portal access logs

Updates `/console/analytics/logs` to the supplied access-log reference: underlined query/application tabs, inline search and download controls, filter tags, a bordered 19-column scrolling table, left pagination, and a tabbed log-details modal. Download application records include refresh, status, progress and file download.

User queries, details and download jobs use the existing customer proxy; downloaded files use the existing authenticated customer download endpoint. Applied filters are retained when requesting a download. Admin routes retain their existing columns and API endpoints. No backend migration or configuration change is required.

Validation: production build, Vue/TypeScript, ESLint, 3 access-log helper tests, and 21 related backend tests (89 assertions) passed. Local mocked browser checks covered both user and admin query/filter/pagination/download/details flows, error recovery, stale responses, deep links, mobile and dark appearance. No production writes or deployment were performed.

## Install

This is a cumulative update package containing current source and compiled frontend assets, including the preceding console updates. Back up the existing installation, extract into `tycdn-backend`, then run:

```sh
php artisan optimize:clear
```

Do not extract into the public directory. No frontend build is needed when using the included `public/build` files.
