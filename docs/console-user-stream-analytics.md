# User stream analytics

Updates `/console/streams/analytics` to the supplied reference layout: underlined 带宽流量 / 端口排行 tabs, inline port and time-range controls, two borderless charts, and a compact sortable port-ranking table.

Bandwidth and traffic use the authenticated customer realtime endpoint; port rankings use the customer top endpoint. Existing admin API routing and appearance remain separate. The charts retain genuine zero samples and show an empty state when no samples exist. Presets, custom dates, TCP/UDP port filters, numeric sorting, per-chart retry, mobile layout, dark mode and URL filters are supported. No backend changes or migration are required.

Validation: frontend build, Vue/TypeScript and ESLint passed; five stream/ranking contract tests passed. Local mocked browser validation exercised both customer and admin scopes, time windows, filtering, sorting, empty states, upstream errors, retries, and mobile/dark layouts with no browser errors or production writes.

## Install

This cumulative ZIP includes the preceding console updates and current compiled assets. Back up your installation, extract into `tycdn-backend`, then run:

```sh
php artisan optimize:clear
```

Do not extract into the public directory. The included `public/build` assets do not require rebuilding. This package has not been deployed.
