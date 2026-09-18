<?php

namespace Tests\Feature;

use App\Models\NetworkProbeSample;
use App\Services\CdnflyApiService;
use App\Services\NetworkMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicNetworkMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['network.targets' => [
            'hk-01' => ['ip' => '156.234.124.162', 'location' => 'hongkong'],
            'hk-02' => ['ip' => '156.234.43.114', 'location' => 'hongkong'],
        ]]);
        $this->mock(CdnflyApiService::class)->shouldReceive('listNodes')->andReturn(['data' => [
            ['pid' => 0, 'type' => 'L1', 'enable' => 1, 'state' => 'done'],
            ['pid' => 0, 'type' => 'L1', 'enable' => 1, 'state' => 'pending'],
            ['pid' => 2, 'type' => 'L1', 'enable' => 1],
            ['pid' => 0, 'type' => 'L2', 'enable' => 1],
            ['pid' => 0, 'type' => 'L1', 'enable' => 0],
        ]]);
    }

    public function test_new_service_shows_inventory_without_fabricated_health_or_history(): void
    {
        Http::preventStrayRequests();
        $response = $this->getJson('/api/network')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.registered_nodes', 2)
            ->assertJsonPath('data.enabled_nodes', 2)
            ->assertJsonPath('data.online_nodes', null)
            ->assertJsonPath('data.latency_ms', null)
            ->assertJsonPath('data.availability_percent', null)
            ->assertJsonPath('data.window_complete', false)
            ->assertJsonPath('data.monitoring_status', 'collecting')
            ->assertJsonPath('data.locations.0.count', 2);
        $this->assertStringNotContainsString('156.234.', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_fresh_samples_produce_real_averages_reachability_and_zero_online(): void
    {
        $this->sample('hk-01', ['latency_total_ms' => 120, 'latency_samples' => 3]);
        $this->sample('hk-02', ['checks' => 3, 'successful_checks' => 1, 'latency_total_ms' => 80, 'latency_samples' => 1]);
        $this->getJson('/api/network')->assertOk()
            ->assertJsonPath('data.online_nodes', 2)
            ->assertJsonPath('data.latency_ms', 50)
            ->assertJsonPath('data.availability_percent', 66.67)
            ->assertJsonPath('data.sample_count', 6)
            ->assertJsonPath('data.history_days', 0)
            ->assertJsonPath('data.window_complete', false)
            ->assertJsonPath('data.monitoring_status', 'ready');

        $this->travel(15)->minutes();
        foreach (['hk-01', 'hk-02'] as $key) {
            $this->sample($key, ['successful_checks' => 0, 'latency_total_ms' => 0, 'latency_samples' => 0]);
        }
        $this->getJson('/api/network')->assertOk()
            ->assertJsonPath('data.online_nodes', 0)
            ->assertJsonPath('data.latency_ms', null)
            ->assertJsonPath('data.locations.0.online_count', 0);
    }

    public function test_stale_or_partial_measurements_do_not_claim_live_health(): void
    {
        $this->sample('hk-01');
        $this->sample('hk-02', ['observed_at' => now()->subHour()]);
        $this->getJson('/api/network')->assertOk()
            ->assertJsonPath('data.online_nodes', null)
            ->assertJsonPath('data.latency_ms', null)
            ->assertJsonPath('data.monitoring_status', 'stale');
    }

    public function test_old_imports_do_not_replace_the_latest_health_and_old_history_is_excluded(): void
    {
        $this->sample('hk-01');
        $this->sample('hk-02');
        $this->sample('hk-01', ['observed_at' => now()->subDays(31), 'successful_checks' => 0]);
        $this->getJson('/api/network')->assertOk()
            ->assertJsonPath('data.online_nodes', 2)
            ->assertJsonPath('data.availability_percent', 100)
            ->assertJsonPath('data.window_complete', false)
            ->assertJsonPath('data.history_days', 30)
            ->assertJsonPath('data.sample_count', 6);
    }

    public function test_thirty_day_label_requires_a_full_well_sampled_window(): void
    {
        // Shorten the expected cadence in this test so a complete window is small.
        config(['network.interval_minutes' => 1440]);
        foreach (range(0, 30) as $day) {
            foreach (['hk-01', 'hk-02'] as $key) {
                $this->sample($key, ['observed_at' => now()->subDays($day)]);
            }
        }
        $this->getJson('/api/network')->assertOk()
            ->assertJsonPath('data.window_complete', true)
            ->assertJsonPath('data.history_days', 30)
            ->assertJsonPath('data.availability_percent', 100);
    }

    public function test_collection_uses_china_probes_handles_polling_and_is_idempotent(): void
    {
        Sleep::fake();
        $result = $this->measurement([
            $this->probe('CN', 3, 40),
            $this->probe('CN', 0, null),
            $this->probe('US', 3, 12),
            ['probe' => ['country' => 'CN'], 'result' => ['status' => 'failed']],
        ]);
        Http::fake([
            'api.globalping.io/v1/measurements' => Http::response(['id' => 'sample-1'], 202),
            'api.globalping.io/v1/measurements/sample-1' => Http::sequence()
                ->push(['status' => 'in-progress'], 200, ['ETag' => 'pending'])
                ->push('', 304)->push($result),
        ]);
        $service = app(NetworkMeasurementService::class);
        $sample = $service->measure('hk-01', '156.234.124.162');
        $this->assertSame(2, $sample->checks);
        $this->assertSame(1, $sample->successful_checks);
        $this->assertSame(1, $sample->latency_samples);
        $this->assertEquals(40, $sample->latency_total_ms);
        $service->storeResult('hk-01', '156.234.124.162', $result);
        $this->assertDatabaseCount('network_probe_samples', 1);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['locations'] === [['country' => 'CN']]
            && $request['limit'] === 3 && $request['measurementOptions']['protocol'] === 'ICMP');
        Http::assertSent(fn ($request) => $request->hasHeader('If-None-Match', 'pending'));
    }

    public function test_provider_failure_does_not_create_downtime_and_command_can_retry(): void
    {
        config(['network.targets' => ['hk-01' => ['ip' => '156.234.124.162', 'location' => 'hongkong']]]);
        Http::fake(['*' => Http::response([], 503)]);
        $this->artisan('network:measure')->assertFailed();
        $this->assertDatabaseCount('network_probe_samples', 0);
        Http::swap(new Factory);
        Http::fake([
            'api.globalping.io/v1/measurements' => Http::response(['id' => 'sample-1'], 202),
            'api.globalping.io/v1/measurements/sample-1' => Http::response($this->measurement([$this->probe('CN', 3, 50)])),
        ]);
        $this->artisan('network:measure')->assertSuccessful();
        $this->assertDatabaseCount('network_probe_samples', 1);
        Http::swap(new Factory);
        Http::fake();
        $this->artisan('network:measure')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_invalid_probe_results_cannot_create_a_sample(): void
    {
        $service = app(NetworkMeasurementService::class);
        foreach ([
            $this->measurement([$this->probe('US', 3, 10)]),
            $this->measurement([['probe' => ['country' => 'CN'], 'result' => ['status' => 'failed']]]),
            array_replace($this->measurement([$this->probe('CN', 3, 20)]), ['target' => '127.0.0.1']),
        ] as $result) {
            try {
                $service->storeResult('hk-01', '156.234.124.162', $result);
                $this->fail('Invalid measurement was accepted.');
            } catch (\RuntimeException) {
                $this->assertDatabaseCount('network_probe_samples', 0);
            }
        }
    }

    private function sample(string $key, array $attributes = []): NetworkProbeSample
    {
        return NetworkProbeSample::create(array_replace([
            'target_key' => $key, 'measurement_id' => (string) Str::uuid(),
            'checks' => 3, 'successful_checks' => 3, 'latency_total_ms' => 150,
            'latency_samples' => 3, 'probe_cities' => ['Beijing'], 'observed_at' => now(),
        ], $attributes));
    }

    private function measurement(array $results): array
    {
        return ['id' => 'sample-1', 'target' => '156.234.124.162', 'type' => 'ping',
            'status' => 'finished', 'createdAt' => now()->subSecond()->toIso8601String(), 'results' => $results];
    }

    private function probe(string $country, int $received, ?float $average): array
    {
        return ['probe' => ['country' => $country, 'city' => 'Beijing'],
            'result' => ['status' => 'finished', 'stats' => ['total' => 3, 'rcv' => $received, 'avg' => $average]]];
    }
}
