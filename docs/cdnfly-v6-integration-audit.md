# CDNfly v6 integration audit

Reviewed against the [v6 user API](https://doc.cdnfly.com/api/v6/user/), [v6 admin API](https://doc.cdnfly.com/api/v6/admin/), the installed master panel's JavaScript, and authenticated read-only responses from the master and portal. The changes are local source changes; they have not been deployed.

The missing values had several independent causes: incorrect response fields, ignored filters, JSON strings treated as ordinary strings, and admin operations routed through customer credentials. A missing value is still shown as missing when CDNfly actually returns null or has no data. Existing records and metric values are not fabricated or backfilled.

| Area | Corrected integration |
| --- | --- |
| Shared tables and totals | Read CDNfly's `count`, including numeric strings and zero, through local/upstream envelopes. Retain support for Laravel's `total`. Re-fetch when a table changes resource. |
| Site rankings | Map `res`, `count`, `traffic`, and `up_recv` across all eight ranking dimensions. Use the documented `60m` time option. |
| Admin monitoring | Use authenticated admin routes for all-customer rankings. Paginate ranking results locally. |
| Login and operation logs | Display `uid`, `success`, `ip_location`, `action`, `type`, and `content`; use native IP/content filters. Customer login logs also use the returned fields. |
| Certificate lists | Recognize `expire_time2`. Certificate search uses `name`. |
| Site filters | Send `domain`, `uid`, and `enable` instead of ignored UI field names. |
| Forwarding lists and forms | Decode `listen` and `backend`; display all ports and origins, package and owner IDs. Send JSON arrays and numeric ports. Require the owner's CDNfly ID for admin creation. Preserve additional listeners, origins, weights, and states when editing through the simplified admin form. |
| Forwarding monitoring | Handle both the installed master's legacy point arrays and newer `outbound`/`inbound` series. Handle both legacy and newer ranking fields. Remove the unsupported per-row timestamp. |
| Usage | Render `date` and `value`. Convert the UI's inclusive last day to CDNfly's exclusive end date. Use the supported `res` filter and paginate the returned series locally. |
| Node editing | Send `enable`, `sort`, and `bw_limit` with Mbps/Gbps units. Remove the ineffective region edit control. |
| WAF libraries | Use native `scope` and rule arrays, preserve existing rule contents, and manage global/user libraries with admin credentials. Remove the obsolete ACL payload schema. Global libraries are read-only from the customer WAF view. |
| CC compatibility resources | Use `item`/`op`/`value` arrays, native actions/operators, numeric matcher/filter IDs, and nullable second filters. Preserve additional matcher properties and rule mode. Admin requests use dedicated routes, mapped CDNfly owner IDs, and the `internal` flag for system/user ownership. |
| API-key management | Dedicated authenticated routes remain accessible after a customer disables their key. Use the account's stored CDNfly ID, ignore caller-supplied ownership, and synchronize encrypted local credentials after success. DELETE passes `uid` in the query. Protect the server's integration credential from changes through account settings. |
| Access-log jobs/downloads | Submit the job's `data.host` field. Stream completed gzip files through an authenticated portal route using customer credentials. JSON/HTTP errors are not downloaded as log files. |
| IP unlock jobs | Send numeric `site_id`. |
| Site details | Decode both serialized and object settings. Read the actual WAF configuration and automatic IP-blocking state. |
| DNS API search | Filter the complete supported list locally because this endpoint does not support name search. |

The installed master still exposes `cc-matchs`, `cc-filters`, and `cc-rules`. These compatibility resources were checked against its panel code; newer protection-profile APIs were not substituted for working installed endpoints. A live read-only check also confirmed that admin API-key lookup with `uid` returns the selected customer's configuration.

Validation:

- Backend regression coverage includes key creation/reset/disable/failure synchronization, owner isolation, admin monitoring and CC authorization, native WAF and node payloads, forwarding ownership/port types, and binary download handling.
- Eight executable JavaScript contract tests cover pagination, disabled keys, nested envelopes, all ranking dimensions, serialized settings, directional monitoring, date boundaries, and CC matcher round trips. Run `npm run test:contracts` in `tycdn-backend`.
- Headless Chrome checks use mocked responses matching the observed master formats. They verify rendered rankings/logs/counts, usage queries and cells, forwarding edits preserving additional configuration, and admin CC form requests.
- TypeScript checking, production asset build, targeted ESLint, PHP formatting, and whitespace checks are part of the final verification. The final run results are recorded below.

Live mutation tests were intentionally not performed on customer sites, DNS, certificates, security rules, or credentials. The browser write checks and PHP HTTP tests use fixtures; production behavior after deployment still needs a smoke check. Existing product tabs explicitly marked as future features were not expanded into new features. No database migration is required.

Deploy the PHP routes/controllers and built console assets together. Existing compiled assets will continue to call the old routes until the new build is deployed. API-key rotation for the master integration account requires a coordinated server configuration update and remains outside the customer account workflow.

Final verification: **283 backend tests passed (1,045 assertions)**; **8 JavaScript contract tests passed**; all four headless-browser scenarios passed without page errors; TypeScript checking, the final production build, targeted ESLint, PHP formatting checks, and `git diff --check` passed. The existing node-management regression test was updated to assert CDNfly's documented `enable` field in both the payload and the recorded HTTP request.
