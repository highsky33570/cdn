# Console WAF log page

`/console/admin/workspace/attack-log` now opens a dedicated WAF page matching the master's `/dashboard/monitor/site/attack-log` layout and workflow.

## Page behavior

- **安全概览** is the default tab. It displays attack total, protected requests, observed requests, automatic blocks, and unique client IPs from the master statistics response.
- The trend chart displays total/protect/observe series, a hover tooltip, and togglable legends. It resizes for mobile and changes colors with the console theme.
- Attack-type distribution includes a doughnut chart and a sortable table with counts and percentage bars.
- Six sortable ranking tables show domains, client IPs with location, countries, provinces, ISPs, and URIs. Clicking a ranking or attack type opens filtered log details.
- **日志明细** displays the native time, domain, action, module, attack type, URI, client IP/location, matched part/key, payload evidence, and a fixed action column. Pagination uses the master's count with 10/30/100 rows per page.
- Both tabs share multi-domain search and advanced filters. The default range covers the complete local calendar day. Second-precision date ranges, URI exact/prefix mode, action, module, category/subtype, matched part/key, payload hash, rule source/ID, port, site/node IDs, status, TLS fingerprint, automatic-block state, country, province, and ISP are retained between tabs. Site ID zero and the master's `auto_blocked=0` filter are preserved.
- Details show attack summary, request information and headers, evidence, and technical metadata. Payload/header text is displayed literally. Missing values and zero values retain their proper meanings.
- Automatically blocked events expose an unlock action. The backend checks the current blacklist before submitting a job restricted to that website/IP and `filter_name=waf_auto_block`.
- The false-positive action adds an exact host and URI-path allow rule through the existing site API. Query parameters are removed from the path, as in the master. Existing canonical log allow rules are merged and deduplicated; unrelated rules and conditions are preserved. Invalid stored configuration produces an error instead of being overwritten.

Errors stay visible and failed overview requests do not appear as successful zero counts. Older responses cannot overwrite a newer query or tab. There are no sample events or simulated statistics in the deployed application.

## API routes

| Console endpoint | Master endpoint |
| --- | --- |
| `GET /api/admin/workspace/attack-stats` (existing) | `GET /v1/monitor/site/attack-log/stats` |
| `GET /api/admin/workspace/attack-log` (existing) | `GET /v1/monitor/site/attack-log` |
| `GET /api/admin/waf-logs/{id}` (new) | `GET /v1/monitor/site/attack-log/{id}` |
| `POST /api/admin/waf-logs/unlock` (new) | Current blacklist check, then `POST /v1/jobs` with a scoped `unlock_ip` job |
| `GET/PUT /api/admin/sites/{id}` (existing) | Read the current site and update only `waf_allow_rule` |

Statistics request `top_size=10` and `types=domain,client_ip,country,province,isp,uri,attack_type`. The new routes use the existing administrator middleware and write limit. There is no change to authentication, currency, schema, or master credentials.

## Live evidence and verification

Read-only authenticated requests to both live applications on 2026-09-22 returned zero WAF log events and five zero summary counts for the complete day. Both returned a zero-valued trend; the master represented empty rankings as `{}`, while the console represented them as `[]`. Both representations are supported.

The live master WAF JavaScript matched the local panel asset SHA-256 `27fc82f5093a8e732e3eb994d871c5637ff444a73ce5eaa75300a17b39226088`. Its native data fields, API paths, filters, detail actions, and allow-rule structure were inspected. No live WAF record was available to exercise event details or writes; those flows use controlled fixtures in tests.

Validation covers:

- 7 PHP tests with 52 assertions: statistics/query forwarding, string document IDs, detail evidence, administrator authorization, scoped unlock payloads, current-block checks, invalid targets, and upstream failures.
- 24 JavaScript contract tests, including WAF response shapes, all rankings and filters, literal evidence, unknown labels, zero/false values, and allow-rule merge/preservation behavior.
- TypeScript, targeted ESLint, PHP formatting, and production build.
- Local Chrome checks for real empty responses, populated fixtures, summary metrics, chart legends, ranking links, every advanced filter, pagination, details, allow-rule creation/deduplication, scoped unlocks, errors/retry, response ordering, light/dark themes, mobile overflow, and node-filter deep links.

No production jobs, allow rules, or configuration were changed during verification.

## Deploy

`.audit/console-validation/tycdn-waf-log-console.zip` contains the changed backend source/tests, this document, and the complete compiled `public/build` directory. Archive paths are relative to the Laravel backend directory containing `artisan`.

1. Extract into that backend directory, merging the included folders. Upload the new controller/routes and matching compiled manifest/assets together. Retain old hashed assets while existing browser tabs may reference them.
2. Run `php artisan optimize:clear`. Reload persistent PHP application workers if used by the deployment.
3. Refresh `/console/admin/workspace/attack-log` and check both tabs. Zero values are expected until the master reports WAF events.

No SQL, migration, dependency installation, or server-side frontend build is required. This patch has been prepared locally and has not been deployed to the live console.
