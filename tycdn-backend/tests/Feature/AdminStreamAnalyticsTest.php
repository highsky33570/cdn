<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminStreamAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cdnfly.base_url' => 'https://cdnfly.example.test', 'services.cdnfly.admin_api_key' => 'master-key', 'services.cdnfly.admin_api_secret' => 'master-secret', 'services.cdnfly.outbound_enabled' => true]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_both_metrics_forward_the_master_range_and_port_with_admin_credentials(): void
    {
        $samples = [[1790112000000, 0], [1790112060000, 125000]];
        Http::fake(['*/v1/monitor/stream/realtime*' => Http::response(['code' => 0, 'data' => $samples])]);
        foreach (['stream-bandwidth', 'stream-traffic'] as $metric) {
            $query = ['type' => $metric, 'start' => '2026-09-23 01:02:03', 'end' => '2026-09-23 07:02:03', 'port' => '88/TCP 99/UDP'];
            $this->getJson('/api/admin/workspace/stream-realtime?'.http_build_query($query))->assertOk()->assertJsonPath('data.data', $samples);
            Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->data() === $query && $request->hasHeader('api-key', 'master-key') && $request->hasHeader('api-secret', 'master-secret'));
        }
        Http::assertSentCount(2);
    }

    public function test_directional_samples_and_empty_results_are_not_flattened_or_fabricated(): void
    {
        $samples = ['outbound' => [[1790112000000, 0]], 'inbound' => [[1790112060000, 1000]]];
        Http::fakeSequence()->push(['code' => 0, 'data' => $samples])->push(['code' => 0, 'data' => []]);
        $this->getJson('/api/admin/workspace/stream-realtime?type=stream-traffic')->assertOk()->assertJsonPath('data.data', $samples);
        $this->getJson('/api/admin/workspace/stream-realtime?type=stream-bandwidth')->assertOk()->assertJsonPath('data.data', []);
    }

    public function test_port_rankings_use_the_master_endpoint_and_native_fields_for_all_windows(): void
    {
        $rows = [['res' => '88/TCP', 'count' => 0, 'traffic' => 1000]];
        Http::fake(['*/v1/monitor/stream/top*' => Http::response(['code' => 0, 'data' => $rows])]);
        foreach (['10m', '30m', '60m'] as $recent) {
            $query = ['type' => 'top-ports', 'recent_time' => $recent];
            $this->getJson('/api/admin/workspace/stream-top?'.http_build_query($query))->assertOk()->assertJsonPath('data.data', $rows);
            Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->data() === $query && $request->hasHeader('api-key', 'master-key'));
        }
        Http::assertSentCount(3);
    }

    public function test_upstream_failures_remain_errors_for_both_admin_resources(): void
    {
        Http::fake(['*' => Http::response(['code' => 1, 'msg' => 'Stream monitoring unavailable'])]);
        foreach (['stream-realtime', 'stream-top'] as $resource) {
            $this->getJson('/api/admin/workspace/'.$resource)->assertStatus(500)->assertJsonPath('ok', false);
        }
    }

    public function test_non_admin_cannot_query_master_stream_monitoring(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach (['stream-realtime', 'stream-top'] as $resource) {
            $this->getJson('/api/admin/workspace/'.$resource)->assertForbidden();
        }
        Http::assertNothingSent();
    }
}
