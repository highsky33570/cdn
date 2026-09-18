<?php

namespace App\Services;

use App\Models\NetworkProbeSample;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class NetworkMeasurementService
{
    private const BASE_URL = 'https://api.globalping.io/v1';

    /** Run only from the scheduler/CLI, never from a visitor's request. */
    public function measure(string $targetKey, string $ip): NetworkProbeSample
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Network measurement requires a public edge IP.');
        }

        $request = Http::acceptJson()->withUserAgent('TyCDN-Network/1.0')->timeout(15);
        $created = $request->post(self::BASE_URL.'/measurements', [
            'type' => 'ping',
            'target' => $ip,
            'locations' => [['country' => 'CN']],
            'limit' => (int) config('network.probe_limit', 3),
            'measurementOptions' => ['packets' => 3, 'protocol' => 'ICMP'],
        ])->throw()->json();

        $id = $created['id'] ?? null;
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new RuntimeException('The probe provider did not return a measurement ID.');
        }

        $etag = null;
        for ($attempt = 0; $attempt < 24; $attempt++) {
            if ($attempt > 0) {
                Sleep::for(600)->milliseconds();
            }
            $response = Http::acceptJson()->withUserAgent('TyCDN-Network/1.0')->timeout(15)
                ->withHeaders($etag ? ['If-None-Match' => $etag] : [])
                ->get(self::BASE_URL.'/measurements/'.$id)->throw();
            if ($response->status() === 304) {
                continue;
            }
            $etag = $response->header('ETag');
            $result = $response->json();
            if (($result['status'] ?? '') === 'in-progress') {
                continue;
            }

            return $this->storeResult($targetKey, $ip, $result);
        }

        throw new RuntimeException('The probe measurement did not finish in time.');
    }

    public function storeResult(string $targetKey, string $ip, array $measurement): NetworkProbeSample
    {
        if (($measurement['status'] ?? '') !== 'finished' || ($measurement['type'] ?? '') !== 'ping'
            || ($measurement['target'] ?? '') !== $ip || empty($measurement['id'])) {
            throw new RuntimeException('Invalid completed edge measurement.');
        }

        $checks = $successful = $latencySamples = 0;
        $latencyTotal = 0.0;
        $cities = [];
        foreach ($measurement['results'] ?? [] as $entry) {
            // Provider errors / unavailable probes are not edge downtime.
            if (data_get($entry, 'probe.country') !== 'CN' || data_get($entry, 'result.status') !== 'finished') {
                continue;
            }
            $sent = data_get($entry, 'result.stats.total');
            $received = data_get($entry, 'result.stats.rcv');
            if (! is_numeric($sent) || (int) $sent <= 0 || ! is_numeric($received)
                || (int) $received < 0 || (int) $received > (int) $sent) {
                continue;
            }
            $checks++;
            $cities[] = (string) data_get($entry, 'probe.city', '');
            if ((int) $received > 0) {
                $successful++;
                $avg = data_get($entry, 'result.stats.avg');
                if (is_numeric($avg) && (float) $avg >= 0 && is_finite((float) $avg)) {
                    $latencyTotal += (float) $avg;
                    $latencySamples++;
                }
            }
        }
        if ($checks === 0) {
            throw new RuntimeException('No completed mainland-China probes were returned.');
        }

        $observed = CarbonImmutable::parse($measurement['createdAt'] ?? '')->utc();
        if (empty($measurement['createdAt']) || $observed->isFuture()) {
            throw new RuntimeException('Invalid measurement timestamp.');
        }

        return NetworkProbeSample::firstOrCreate(['measurement_id' => $measurement['id']], [
            'target_key' => $targetKey,
            'checks' => $checks,
            'successful_checks' => $successful,
            'latency_total_ms' => $latencyTotal,
            'latency_samples' => $latencySamples,
            'probe_cities' => array_values(array_unique(array_filter($cities))),
            'observed_at' => $observed,
        ]);
    }
}
