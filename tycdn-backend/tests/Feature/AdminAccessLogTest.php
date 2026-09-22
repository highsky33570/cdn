<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAccessLogTest extends TestCase
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

    public function test_jobs_are_limited_to_access_log_downloads_with_master_credentials(): void
    {
        Http::fake(['*/v1/jobs*' => Http::response(['code' => 0, 'count' => 31, 'data' => [
            ['id' => 9, 'task_id' => 10, 'state' => 'process', 'progress' => '1%', 'data' => '{"host":"example.test"}'],
        ]])]);
        $this->getJson('/api/admin/access-log-jobs?page=2&limit=30&type=unlock_ip&uid=10')
            ->assertOk()->assertJsonPath('data.count', 31)->assertJsonPath('data.data.0.progress', '1%');
        Http::assertSent(fn (Request $request) => $request->hasHeader('api-key', 'master-key')
            && $request->method() === 'GET' && $request->data() === ['page' => '2', 'limit' => '30', 'type' => 'down_http_access_log']);
    }

    public function test_download_request_keeps_all_applied_filters_without_pagination(): void
    {
        Http::fake(['*/v1/jobs' => Http::response(['code' => 0, 'data' => 9])]);
        $filters = [
            'start' => '2026-09-22 00:00:00', 'end' => '2026-09-23 00:00:00',
            'host' => 'example.test', 'addr' => '2001:db8::1', 'req_uri' => '/api/', 'uri_match_type' => 'prefix',
            'method' => 'GET', 'status' => '200', 'cache_status' => 'MISS', 'server_port' => '443',
            'node_id' => '0', 'tls_fp' => 'test-fingerprint', 'referer' => 'https://example.test/',
            'country' => '中国', 'province' => '广东省', 'isp' => '中国移动',
        ];
        $this->postJson('/api/admin/access-log-jobs', $filters + ['page' => 3, 'limit' => 10, 'type' => 'unlock_ip', 'uid' => 5])
            ->assertOk()->assertJsonPath('data.data', 9);
        $filters['server_port'] = 443;
        $filters['node_id'] = 0;
        Http::assertSent(function (Request $request) use ($filters): bool {
            $this->assertEquals($filters, $request['data']);

            return $request->method() === 'POST' && $request['type'] === 'down_http_access_log'
                && $request['data']['node_id'] === 0 && $request['data']['server_port'] === 443
                && $request->hasHeader('api-key', 'master-key');
        });
    }

    public function test_invalid_download_ranges_and_ports_are_rejected_without_master_requests(): void
    {
        $valid = ['start' => '2026-09-22 00:00:00', 'end' => '2026-09-23 00:00:00'];
        foreach ([[], ['start' => '2026-09-22'], ['end' => '2026-09-21 00:00:00'],
            ['server_port' => 70000], ['node_id' => -1], ['uri_match_type' => 'regex'],
        ] as $override) {
            $this->postJson('/api/admin/access-log-jobs', $override === [] ? [] : array_replace($valid, $override))->assertUnprocessable();
        }
        $this->getJson('/api/admin/access-log-jobs?limit=1000')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_log_details_accept_the_masters_string_document_ids(): void
    {
        $id = 'ohMyyKABzku50BFxrdtj';
        Http::fake(['*/v1/monitor/site/access-log/'.$id => Http::response(['code' => 0, 'data' => [
            'req_header' => 'Host: example.test', 'resp_header' => '-', 'req_body' => base64_encode('你好'),
        ]])]);
        $this->getJson('/api/admin/access-logs/'.$id)->assertOk()->assertJsonPath('data.data.req_header', 'Host: example.test');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->hasHeader('api-key', 'master-key'));
        Http::assertSentCount(1);
    }

    public function test_ready_files_stream_binary_with_admin_credentials(): void
    {
        $gzip = gzencode('access log data');
        Http::fake(['*/v1/monitor/site/download-access-log/9' => Http::response($gzip, 200, ['Content-Type' => 'application/gzip'])]);
        $this->get('/api/admin/access-log-jobs/9/download')->assertOk()->assertDownload('access-log-9.gz')->assertStreamedContent($gzip);
        Http::assertSent(fn (Request $request) => $request->hasHeader('api-key', 'master-key') && $request->method() === 'GET');
    }

    public function test_failed_downloads_and_job_requests_report_upstream_errors(): void
    {
        Http::fake(['*' => Http::response(['code' => 'job-pending', 'msg' => '文件尚未生成'])]);
        $this->getJson('/api/admin/access-log-jobs/9/download')->assertStatus(500)->assertJsonPath('ok', false);
        $this->getJson('/api/admin/access-log-jobs')->assertStatus(500)->assertJsonPath('ok', false);
        $this->postJson('/api/admin/access-log-jobs', ['start' => '2026-09-22 00:00:00', 'end' => '2026-09-23 00:00:00'])
            ->assertStatus(500)->assertJsonPath('ok', false);
    }

    public function test_non_admin_users_cannot_access_admin_jobs_details_or_downloads(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/access-log-jobs')->assertForbidden();
        $this->postJson('/api/admin/access-log-jobs', [])->assertForbidden();
        $this->getJson('/api/admin/access-log-jobs/9/download')->assertForbidden();
        $this->getJson('/api/admin/access-logs/ohMyyKABzku50BFxrdtj')->assertForbidden();
        Http::assertNothingSent();
    }
}
