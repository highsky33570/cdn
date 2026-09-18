<?php

namespace App\Console\Commands;

use App\Models\NetworkProbeSample;
use App\Services\NetworkMeasurementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MeasurePublicNetwork extends Command
{
    protected $signature = 'network:measure';

    protected $description = 'Collect mainland-China edge latency and sampled availability';

    public function handle(NetworkMeasurementService $measurements): int
    {
        if (! config('network.monitoring_enabled', true)) {
            $this->info('Network measurement is disabled.');

            return self::SUCCESS;
        }
        $lock = Cache::lock('network:measure', 600);
        if (! $lock->get()) {
            $this->info('A measurement round is already running.');

            return self::SUCCESS;
        }
        $failures = 0;
        try {
            foreach (config('network.targets', []) as $key => $target) {
                if (NetworkProbeSample::where('target_key', $key)
                    ->where('observed_at', '>=', now()->subMinutes((int) config('network.interval_minutes', 15) - 1))->exists()) {
                    continue;
                }
                try {
                    $sample = $measurements->measure($key, $target['ip']);
                    $this->info("{$key}: {$sample->successful_checks}/{$sample->checks} probes reached the edge.");
                } catch (\Throwable $error) {
                    $failures++;
                    // Provider failures do not become fabricated downtime samples.
                    $this->warn("{$key}: measurement unavailable (".class_basename($error).').');
                }
            }
            NetworkProbeSample::where('observed_at', '<', now()->subDays(35))->delete();
        } finally {
            $lock->release();
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
