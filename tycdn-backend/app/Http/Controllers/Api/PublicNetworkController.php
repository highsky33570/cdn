<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public, unauthenticated network summary for the marketing site.
 *
 * The landing page must never call the admin API directly, and it must not
 * publish fabricated figures. This exposes only what is real and safe to show:
 * the count of online edge nodes and a per-location breakdown, computed
 * server-side from CDNfly's node list and cached so a burst of marketing views
 * cannot hammer the master.
 *
 * Geographic location is not structured data in CDNfly (every node sits in the
 * "默认" region), so location is derived from the node name prefix
 * (hongkong-05 → "hongkong"); the marketing site maps known prefixes to map
 * coordinates. Latency and uptime are deliberately NOT returned — those require
 * real probe/monitoring data and are left for the site to show as pending.
 */
class PublicNetworkController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(): JsonResponse
    {
        $summary = Cache::remember('public_network_summary', 300, function (): array {
            try {
                $result = $this->cdnfly->listNodes(['limit' => 0]);
            } catch (\Throwable) {
                return ['online_nodes' => 0, 'locations' => []];
            }

            $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
            $online = 0;
            $byLocation = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                // Main edge machines only: skip sub-IP child records and L2 nodes.
                if ((int) ($row['pid'] ?? 0) !== 0) {
                    continue;
                }

                $type = strtoupper((string) ($row['type'] ?? 'L1'));

                if ($type !== '' && $type !== 'L1') {
                    continue;
                }

                // CDNfly marks a healthy node's state as "正常".
                if ((string) ($row['state'] ?? '') !== '正常') {
                    continue;
                }

                $online++;

                $name = (string) ($row['name'] ?? '');
                $key = preg_replace('/-\d+$/', '', $name);
                $key = $key !== '' ? $key : 'other';
                $byLocation[$key] = ($byLocation[$key] ?? 0) + 1;
            }

            $locations = [];

            foreach ($byLocation as $key => $count) {
                $locations[] = ['key' => $key, 'count' => $count];
            }

            return ['online_nodes' => $online, 'locations' => $locations];
        });

        return response()->json(['ok' => true, 'data' => $summary]);
    }
}
