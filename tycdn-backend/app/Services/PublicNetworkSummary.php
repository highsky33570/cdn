<?php

namespace App\Services;

use App\Models\NetworkProbeSample;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PublicNetworkSummary
{
    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    public function get(): array
    {
        $targets = config('network.targets', []);
        $keys = array_keys($targets);
        $now = CarbonImmutable::now('UTC');
        $cutoff = $now->subDays(30);
        $freshAfter = $now->subMinutes((int) config('network.fresh_minutes', 35));
        $summary = [
            'online_nodes' => null,
            'registered_nodes' => count($targets),
            'enabled_nodes' => $this->enabledNodes(),
            'latency_ms' => null,
            'availability_percent' => null,
            'history_days' => 0,
            'window_complete' => false,
            'sample_count' => 0,
            'sampling_coverage_percent' => 0,
            'last_checked_at' => null,
            'monitoring_status' => 'collecting',
            'locations' => [],
        ];

        $latest = collect();
        if ($keys !== [] && Schema::hasTable('network_probe_samples')) {
            $base = NetworkProbeSample::query()->whereIn('target_key', $keys)->where('observed_at', '<=', $now);
            $firstAt = (clone $base)->min('observed_at');
            $window = (clone $base)->where('observed_at', '>=', $cutoff)->where('observed_at', '<=', $now);
            $totals = (clone $window)->selectRaw('SUM(checks) as checks, SUM(successful_checks) as successes, COUNT(*) as rounds')->first();
            $checks = (int) $totals->checks;
            // Order by observation time, including when importing older samples.
            $latest = collect($keys)->map(fn ($key) => (clone $base)->where('target_key', $key)
                ->orderByDesc('observed_at')->orderByDesc('id')->first())->filter()->keyBy('target_key');
            $fresh = $latest->filter(fn ($row) => $row->observed_at->greaterThanOrEqualTo($freshAfter));
            $complete = $fresh->count() === count($targets);
            $latencyCount = (int) $fresh->sum('latency_samples');
            $summary['online_nodes'] = $complete ? $fresh->filter(fn ($row) => $row->successful_checks > 0)->count() : null;
            $summary['latency_ms'] = $complete && $latencyCount > 0 ? round($fresh->sum('latency_total_ms') / $latencyCount, 1) : null;
            $summary['monitoring_status'] = $complete ? 'ready' : ($latest->isNotEmpty() ? 'stale' : 'collecting');
            $summary['last_checked_at'] = $latest->max('observed_at')?->toIso8601String();
            $summary['sample_count'] = $checks;
            $summary['availability_percent'] = $checks > 0 ? round((int) $totals->successes / $checks * 100, 2) : null;
            if ($firstAt) {
                $first = CarbonImmutable::parse($firstAt, 'UTC');
                $days = max(0, $first->diffInDays($now));
                $summary['history_days'] = min(30, (int) floor($days));
                $minutes = min(30 * 24 * 60, max(0, $first->diffInMinutes($now)));
                $expected = max(1, ceil($minutes / max(1, (int) config('network.interval_minutes', 15)))) * count($targets);
                $coverage = min(100, round((int) $totals->rounds / $expected * 100, 1));
                $summary['sampling_coverage_percent'] = $coverage;
                $summary['window_complete'] = $days >= 30 && $coverage >= 95;
            }
        }

        foreach (config('network.locations', []) as $key => $location) {
            $regionKeys = array_keys(array_filter($targets, fn ($target) => $target['location'] === $key));
            if ($regionKeys === []) {
                continue;
            }
            $regionFresh = $latest->only($regionKeys)->filter(fn ($row) => $row->observed_at->greaterThanOrEqualTo($freshAfter));
            $summary['locations'][] = [
                'key' => $key,
                ...$location,
                'count' => count($regionKeys),
                'online_count' => $regionFresh->count() === count($regionKeys)
                    ? $regionFresh->filter(fn ($row) => $row->successful_checks > 0)->count() : null,
            ];
        }

        return $summary;
    }

    private function enabledNodes(): ?int
    {
        // CDNfly's `state` is configuration sync, not health. Never compare it to 正常.
        try {
            return Cache::remember('public_network_enabled_v2', 60, function () {
                $result = $this->cdnfly->listNodes(['limit' => 0]);
                if (! empty($result['cdnfly_outbound_disabled']) || ! is_array($result['data'] ?? null)) {
                    return null;
                }

                return count(array_filter($result['data'], fn ($node) => is_array($node)
                    && (int) ($node['pid'] ?? 0) === 0
                    && in_array(strtoupper((string) ($node['type'] ?? 'L1')), ['', 'L1'], true)
                    && (int) ($node['enable'] ?? 0) === 1));
            });
        } catch (\Throwable) {
            return null;
        }
    }
}
