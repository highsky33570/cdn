<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWafLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cdnfly.base_url' => 'https://cdnfly.example.test', 'services.cdnfly.admin_api_key' => 'master-key', 'services.cdnfly.admin_api_secret' => 'master-secret', 'services.cdnfly.outbound_enabled' => true]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_waf_stats_and_list_keep_native_filters_and_zero_results(): void
    {
        Http::fake([
            '*/v1/monitor/site/attack-log/stats*' => Http::response(['code' => 0, 'data' => ['total' => 0, 'protect' => 0, 'observe' => 0, 'auto_blocked' => 0, 'unique_ip' => 0, 'top' => [], 'trend' => [['time' => '2026-09-22 00:00:00', 'total' => 0, 'protect' => 0, 'observe' => 0]]]]),
            '*/v1/monitor/site/attack-log*' => Http::response(['code' => 0, 'count' => 35, 'data' => []]),
        ]);
        $query = ['start' => '2026-09-22 00:00:00', 'end' => '2026-09-23 00:00:00', 'host' => 'one.test two.test', 'client_ip' => '192.0.2.1', 'request_uri' => '/api/', 'uri_match_type' => 'prefix', 'module' => 'sqli', 'attack_category' => 'sqli', 'attack_subtype' => 'union', 'waf_matched_part' => 'arg', 'waf_matched_key' => 'q', 'waf_payload_hash' => 'hash', 'site_id' => '0', 'node_id' => '3', 'status' => '403', 'tls_fp' => 'fp', 'auto_blocked' => 'false', 'country' => '中国', 'province' => '广东省', 'isp' => '中国移动', 'action' => 'protect', 'rule_source' => 'builtin', 'rule_id' => 'test-rule', 'server_port' => '443'];
        $this->getJson('/api/admin/workspace/attack-stats?'.http_build_query($query + ['top_size' => 10, 'types' => 'domain,client_ip,country,province,isp,uri,attack_type']))->assertOk()->assertJsonPath('data.data.total', 0)->assertJsonPath('data.data.trend.0.protect', 0);
        $this->getJson('/api/admin/workspace/attack-log?'.http_build_query($query + ['page' => 2, 'limit' => 30]))->assertOk()->assertJsonPath('data.count', 35);
        Http::assertSent(function (Request $request) use ($query): bool {
            if (! str_contains($request->url(), '/stats')) {
                return false;
            }
            foreach ($query as $key => $value) {
                $this->assertSame($value, $request[$key]);
            }

            return $request->hasHeader('api-key', 'master-key') && $request['top_size'] === '10';
        });
    }

    public function test_detail_accepts_native_document_ids_and_returns_all_evidence(): void
    {
        Http::fake(['*/v1/monitor/site/attack-log/doc_1-abc' => Http::response(['code' => 0, 'data' => ['site_id' => '0', 'req_header' => '{"x-test":"<script>"}', 'waf_payload_sample' => 'UNION SELECT', 'auto_blocked' => true]])]);
        $this->getJson('/api/admin/waf-logs/doc_1-abc')->assertOk()->assertJsonPath('data.data.site_id', '0')->assertJsonPath('data.data.waf_payload_sample', 'UNION SELECT');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->hasHeader('api-key', 'master-key'));
    }

    public function test_unlock_checks_current_waf_block_and_submits_only_the_scoped_job(): void
    {
        Http::fake(['*/v1/monitor/site/blackip*' => Http::response(['code' => 0, 'count' => 1, 'data' => [['site_id' => 0]]]), '*/v1/jobs' => Http::response(['code' => 0, 'data' => 77])]);
        $this->postJson('/api/admin/waf-logs/unlock', ['site_id' => '0', 'ip' => '2001:db8::1', 'filter_name' => 'other-rule'])->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->data() === ['site_id' => 0, 'ip' => '2001:db8::1', 'filter_name' => 'waf_auto_block', 'page' => 1, 'limit' => 1]);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === [['type' => 'unlock_ip', 'data' => ['site_id' => 0, 'ip' => '2001:db8::1', 'filter_name' => 'waf_auto_block', 'key1' => 'site_id']]]);
    }

    public function test_unlock_without_a_current_waf_block_does_not_submit_a_job(): void
    {
        Http::fake(['*/v1/monitor/site/blackip*' => Http::response(['code' => 0, 'count' => 0, 'data' => []])]);
        $this->postJson('/api/admin/waf-logs/unlock', ['site_id' => 3, 'ip' => '192.0.2.1'])->assertStatus(409)->assertJsonPath('ok', false);
        Http::assertSentCount(1);
    }

    public function test_invalid_unlock_targets_are_rejected_without_requests(): void
    {
        foreach ([[], ['site_id' => -1, 'ip' => '192.0.2.1'], ['site_id' => 0], ['site_id' => 0, 'ip' => 'not-an-ip']] as $target) {
            $this->postJson('/api/admin/waf-logs/unlock', $target)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_upstream_errors_are_visible_instead_of_empty_successes(): void
    {
        Http::fake(['*' => Http::response(['code' => 1, 'msg' => 'WAF unavailable'])]);
        $this->getJson('/api/admin/waf-logs/doc_1')->assertStatus(500)->assertJsonPath('ok', false);
        $this->postJson('/api/admin/waf-logs/unlock', ['site_id' => 0, 'ip' => '192.0.2.1'])->assertStatus(500)->assertJsonPath('ok', false);
    }

    public function test_non_admin_cannot_read_waf_details_or_unlock_blocks(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/waf-logs/doc_1')->assertForbidden();
        $this->postJson('/api/admin/waf-logs/unlock', ['site_id' => 0, 'ip' => '192.0.2.1'])->assertForbidden();
        Http::assertNothingSent();
    }
}
