# Public network measurements

The landing page reads `GET /api/network`. Both maps and the three metric tiles share this request and refresh once per minute. Public responses contain aggregate regions and measurements, never the target IP inventory.

## Start collecting on the deployed portal backend

Run from `tycdn-backend`:

```sh
php artisan migrate --force
php artisan network:measure
```

Ensure the existing Laravel scheduler runs every minute (replace the deployment path):

```cron
* * * * * cd /path/to/tycdn-backend && php artisan schedule:run >> /dev/null 2>&1
```

The registered `network:measure` job runs every 15 minutes. Use `php artisan schedule:list` to verify it. On a Windows development machine, run `php artisan schedule:work` in a dedicated terminal. A successful local measurement does not start monitoring on the production server.

No panel credentials or additional probe server are required. The backend needs outbound HTTPS access to `api.globalping.io`. The [Globalping API](https://globalping.io/docs/api.globalping.io) provides public probes selected with country `CN`. If the provider is unavailable, rate limited, or has no suitable probes, existing history is retained and current statistics become stale.

The five operator-supplied targets are configured in `tycdn-backend/config/network.php`. Public IP geolocation places them in Hong Kong; update the region if the operator confirms a different physical location. Map connection lines are illustrative audience paths, not extra PoPs or measured routing. The frontend region inventory in `frontend/src/data/network.js` is the initial/offline fallback and should be updated alongside this configuration.

## Definitions

- **中国大陆平均延迟**: mean of the successful CN probes' average ICMP round-trip times in the latest fresh round per target. Three probes send three packets each per target. Probe locations may vary between rounds; this is a sample, not a nationwide traffic-weighted benchmark.
- **在线边缘节点**: configured targets with at least one successful CN probe in their latest fresh round. All targets need a result within 35 minutes before the UI labels the count online. Before then, the UI shows the registered target count as **已接入边缘节点**. A measured zero remains zero.
- **可用性**: successful completed probe checks / all completed probe checks × 100, across the most recent 30 days. A check succeeds if at least one packet receives a reply. This measures sampled network reachability, not HTTP service availability or a contractual SLA. Provider failures are excluded rather than recorded as edge downtime.
- **History**: before 30 days, the UI says **监测以来可用性** and shows the collected days. The **近 30 天可用性** label requires at least 30 days of history and 95% of expected measurement rounds. A sparse older history is labeled **近 30 天抽样可用性**. API field `sampling_coverage_percent` reports round coverage separately from reachability.

CDNfly's `state` describes configuration synchronization (for example, `done`), not connectivity. The optional `enabled_nodes` field counts enabled main L1 records from the panel separately; it is never substituted for measured health.

The collector uses a cache lock, avoids immediate repeat samples, and deduplicates provider measurement IDs. It retains 35 days of history. At the default cadence, five targets × three probes × four rounds consume about 60 probe tests/hour. Set `NETWORK_MONITORING_ENABLED=false` to disable scheduled collection; refresh Laravel's configuration cache after environment changes.

For actual website availability, add a representative HTTPS health endpoint through the CDN and monitor its HTTP status/body from multiple regions. Checking `panel.tycdn.org` alone measures the management panel, not customers' CDN requests.

## Verification

```sh
php artisan test --compact --filter=PublicNetworkMonitoringTest
```

Tests cover new-service history, sync-state handling, real means and zeros, stale/incomplete rounds, the 30-day window, out-of-order samples, mainland-only probes, polling, deduplication, and provider failure/retry behavior.
