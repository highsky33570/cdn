<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBlockLogTest extends TestCase
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

    public function test_statistics_use_the_admin_master_endpoint_and_preserve_site_zero(): void
    {
        Http::fake(['*/v1/monitor/site/blackip-count' => Http::response([
            'code' => 0, 'count' => 1, 'data' => [['site_id' => '0', 'count' => 1]],
        ])]);
        $this->getJson('/api/admin/workspace/blackip-count')->assertOk()
            ->assertJsonPath('data.data.0.site_id', '0')->assertJsonPath('data.count', 1);
        Http::assertSent(fn (Request $request) => $request->hasHeader('api-key', 'master-key') && $request->method() === 'GET');
        Http::assertSentCount(1);
    }

    public function test_listing_preserves_current_filters_and_history_epoch_seconds(): void
    {
        Http::fake(['*/v1/monitor/site/*' => Http::response(['code' => 0, 'count' => 35, 'data' => []])]);
        $queries = [
            'blackip' => ['page' => 2, 'limit' => 30, 'site_id' => '0', 'ip' => '192.0.2.1', 'filter_name' => '9'],
            'history-blackip' => ['page' => 2, 'limit' => 10, 'start' => 1790000000, 'end' => 1790050000, 'site_id' => '0'],
        ];
        foreach ($queries as $resource => $query) {
            $this->getJson('/api/admin/workspace/'.$resource.'?'.http_build_query($query))
                ->assertOk()->assertJsonPath('data.count', 35);
            Http::assertSent(fn (Request $request) => str_contains($request->url(), '/'.$resource.'?')
                && collect($query)->every(fn ($value, $key) => (string) $request[$key] === (string) $value));
        }
    }

    public function test_selected_and_site_wide_unlocks_submit_the_exact_master_job_contract(): void
    {
        Http::fake(['*/v1/jobs' => Http::response(['code' => 0, 'data' => 101])]);
        $this->postJson('/api/admin/workspace/blackip/unlock', ['items' => [
            ['site_id' => '0', 'ip' => '192.0.2.1'],
            ['site_id' => 12, 'ip' => '2001:db8::1'],
        ]])->assertOk();
        $this->postJson('/api/admin/workspace/blackip/unlock', ['items' => [['site_id' => 12]]])->assertOk();
        $requests = Http::recorded()->map(fn ($pair) => $pair[0])->all();
        $this->assertSame([
            ['type' => 'unlock_ip', 'data' => ['site_id' => 0, 'key1' => 'site_id', 'ip' => '192.0.2.1']],
            ['type' => 'unlock_ip', 'data' => ['site_id' => 12, 'key1' => 'site_id', 'ip' => '2001:db8::1']],
        ], $requests[0]->data());
        $this->assertSame([['type' => 'unlock_ip', 'data' => ['site_id' => 12, 'key1' => 'site_id']]], $requests[1]->data());
        $this->assertTrue($requests[0]->hasHeader('api-key', 'master-key'));
        $this->assertSame('POST', $requests[0]->method());
    }

    public function test_invalid_unlock_targets_cannot_become_site_wide_jobs(): void
    {
        foreach ([[], [['site_id' => -1]], [['ip' => '192.0.2.1']], [['site_id' => '']],
            [['site_id' => 0, 'ip' => '']], [['site_id' => 0, 'ip' => 'invalid']],
            [['site_id' => 0, 'type' => 'delete_site']],
        ] as $items) {
            $this->postJson('/api/admin/workspace/blackip/unlock', ['items' => $items])->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_exports_stream_the_full_filtered_master_text_without_pagination_or_credentials_in_query(): void
    {
        Http::fake(['*/v1/monitor/site/*' => fn () => Http::response("192.0.2.1\n2001:db8::1", 200, ['Content-Type' => 'text/plain'])]);
        foreach (['blackip' => 'black_ip.txt', 'history-blackip' => 'history_black_ip.txt'] as $resource => $filename) {
            $this->get('/api/admin/workspace/'.$resource.'/export?'.http_build_query([
                'site_id' => '0', 'ip' => '192.0.2.1', 'filter_name' => '9',
                'start' => 1790000000, 'end' => 1790050000,
                'page' => 2, 'limit' => 10, 'access_token' => 'do-not-forward',
            ]))->assertOk()->assertDownload($filename)->assertStreamedContent("192.0.2.1\n2001:db8::1");
        }
        foreach (Http::recorded() as [$request]) {
            $this->assertSame('export', $request['action']);
            $this->assertSame('0', $request['site_id']);
            $this->assertSame('9', $request['filter_name']);
            $this->assertSame('1790000000', $request['start']);
            $this->assertArrayNotHasKey('limit', $request->data());
            $this->assertArrayNotHasKey('page', $request->data());
            $this->assertArrayNotHasKey('access_token', $request->data());
            $this->assertTrue($request->hasHeader('api-key', 'master-key'));
        }
    }

    public function test_export_failure_is_not_downloaded_as_a_successful_text_file(): void
    {
        Http::fake(['*/v1/monitor/site/*' => Http::response(['code' => 'monitor-1', 'msg' => '查询失败'])]);
        $this->getJson('/api/admin/workspace/blackip/export')->assertStatus(500)->assertJsonPath('ok', false);
        $this->postJson('/api/admin/workspace/history-blackip/export')->assertMethodNotAllowed();
    }

    public function test_export_rejects_partial_or_reversed_time_ranges(): void
    {
        foreach (['start=1790000000', 'end=1790000000', 'start=1790050000&end=1790000000'] as $query) {
            $this->getJson('/api/admin/workspace/history-blackip/export?'.$query)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_non_admin_users_cannot_list_export_or_unlock_global_blocks(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/workspace/blackip-count')->assertForbidden();
        $this->getJson('/api/admin/workspace/blackip/export')->assertForbidden();
        $this->postJson('/api/admin/workspace/blackip/unlock', ['items' => [['site_id' => 0]]])->assertForbidden();
        Http::assertNothingSent();
    }
}
