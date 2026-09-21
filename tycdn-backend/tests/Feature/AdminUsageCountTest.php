<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminUsageCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'master-key',
            'services.cdnfly.admin_api_secret' => 'master-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_reported_timestamps_are_normalized_before_calling_the_master(): void
    {
        Http::fake(['*/v1/monitor/usage-count*' => Http::response([
            'code' => 0, 'data' => ['bandwidth_value' => 1234, 'blackip_value' => 0, 'req_value' => 42, 'traffic_value' => 4567],
        ])]);

        $this->getJson('/api/admin/workspace/usage-count?'.http_build_query([
            'start' => '2026-09-20 16:08:02', 'end' => '2026-09-21 16:08:02',
        ]))->assertOk()->assertJsonPath('data.data.req_value', 42)->assertJsonPath('data.data.blackip_value', 0);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request['start'] === '2026-09-20' && $request['end'] === '2026-09-22'
            && $request->hasHeader('api-key', 'master-key'));
    }

    public function test_date_only_and_midnight_end_boundaries_are_not_extended(): void
    {
        Http::fake(['*/v1/monitor/usage-count*' => Http::response(['code' => 0, 'data' => []])]);

        foreach (['2026-09-22', '2026-09-22 00:00:00'] as $end) {
            $this->getJson('/api/admin/workspace/usage-count?'.http_build_query([
                'start' => '2026-09-21', 'end' => $end,
            ]))->assertOk();
        }
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame('2026-09-21', $request['start']);
            $this->assertSame('2026-09-22', $request['end']);
        }
    }

    public function test_partial_end_days_roll_over_across_year_and_leap_day_boundaries(): void
    {
        Http::fake(['*/v1/monitor/usage-count*' => Http::response(['code' => 0, 'data' => []])]);
        foreach ([['2026-12-31', '2027-01-01'], ['2028-02-28', '2028-02-29'], ['2028-02-29', '2028-03-01']] as [$day, $next]) {
            $this->getJson('/api/admin/workspace/usage-count?'.http_build_query([
                'start' => $day.' 00:00:00', 'end' => $day.' 23:59:59',
            ]))->assertOk();
            Http::assertSent(fn (Request $request) => $request['start'] === $day && $request['end'] === $next);
        }
        Http::assertSentCount(3);
    }

    public function test_invalid_ranges_are_validation_errors_without_upstream_requests(): void
    {
        foreach ([
            ['start' => '2026-02-30', 'end' => '2026-03-02'],
            ['start' => '2026-09-21', 'end' => '2026-09-21'],
            ['start' => '2026-09-22', 'end' => '2026-09-21'],
            ['start' => '2026-09-20', 'end' => '2026-09-21 25:08:02'],
            ['start' => ['2026-09-21'], 'end' => '2026-09-22'],
            ['end' => '2026-09-22'],
        ] as $query) {
            $this->getJson('/api/admin/workspace/usage-count?'.http_build_query($query))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_realtime_chart_timestamps_keep_their_time_precision(): void
    {
        Http::fake(['*/v1/monitor/site/realtime*' => Http::response(['code' => 0, 'data' => []])]);
        $query = ['type' => 'qps', 'start' => '2026-09-20 16:08:02', 'end' => '2026-09-21 16:08:02'];
        $this->getJson('/api/admin/workspace/site-realtime?'.http_build_query($query))->assertOk();
        Http::assertSent(fn (Request $request) => $request['start'] === $query['start'] && $request['end'] === $query['end']);
    }
}
