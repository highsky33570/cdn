<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as UpstreamRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminFirewallTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/v1/configs/global-0-openresty_config-openresty-config';

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_global_cc_switch_round_trips_native_booleans_through_the_real_api_service(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'master-key',
            'services.cdnfly.admin_api_secret' => 'master-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);
        Http::preventStrayRequests();
        $master = ['cc_enable' => false, 'waf_enable' => 1, 'key' => 'preserved', 'slider_html' => '<html>keep</html>'];
        Http::fake(function (UpstreamRequest $request) use (&$master) {
            $this->assertSame('https://cdnfly.example.test'.self::PATH, $request->url());
            $this->assertTrue($request->hasHeader('api-key', 'master-key'));
            if ($request->method() === 'PUT') {
                $master = json_decode($request['value'], true);
                $this->assertIsBool($master['cc_enable']);

                return Http::response(['code' => 0]);
            }

            return Http::response(['code' => 0, 'data' => ['value' => json_encode($master)]]);
        });
        $this->admin();
        foreach ([true, false, 1, 0, '1', '0'] as $value) {
            $expected = (bool) $value;
            $this->putJson('/api/admin/firewall', ['patch' => ['cc_enable' => $value]])->assertOk()->assertJsonPath('data.cc_enable', $expected);
            // Native panel switches compare to literal true/false, not truthiness.
            $this->assertSame($expected, $master['cc_enable']);
            $this->assertSame('preserved', $master['key']);
            $this->assertSame('<html>keep</html>', $master['slider_html']);
            $this->getJson('/api/admin/firewall')->assertOk()->assertJsonPath('data.cc_enable', $expected);
        }
        $master['cc_enable'] = true;
        $this->getJson('/api/admin/firewall')->assertOk()->assertJsonPath('data.cc_enable', true);
        $master['cc_enable'] = false;
        $this->getJson('/api/admin/firewall')->assertOk()->assertJsonPath('data.cc_enable', false);
    }

    public function test_all_firewall_endpoints_require_admin(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/firewall')->assertForbidden();
        $this->putJson('/api/admin/firewall', ['patch' => ['cc_enable' => 0]])->assertForbidden();
        $this->getJson('/api/admin/firewall/overrides')->assertForbidden();
        $this->putJson('/api/admin/firewall/overrides/node/1', ['patch' => ['cc_enable' => 0]])->assertForbidden();
        $this->deleteJson('/api/admin/firewall/overrides/node/1')->assertForbidden();
        $this->postJson('/api/admin/firewall/images')->assertForbidden();
    }

    public function test_reads_mask_keys_and_force_firewall_scope(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => json_encode(['key' => 'real-key', 'cc_enable' => 0])]]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs', ['page' => '1', 'limit' => '10', 'type' => 'openresty_config', 'name' => 'openresty-config', 'scope_name' => 'region,node'])->andReturn(['count' => 1, 'data' => [['scope_name' => 'node', 'scope_id' => 1, 'value' => '{"key":"node-key","waf_enable":0}']]]);
        $this->admin();
        $this->getJson('/api/admin/firewall')->assertOk()->assertJsonPath('data.key', ConfigSecrets::MASK)->assertJsonPath('data.cc_enable', 0);
        $response = $this->getJson('/api/admin/firewall/overrides?page=1&limit=10&type=other&scope_name=global')->assertOk();
        $this->assertSame(['key' => ConfigSecrets::MASK, 'waf_enable' => 0], json_decode($response->json('data.data.0.value'), true));
    }

    public function test_patch_preserves_secrets_templates_and_unrelated_nested_values(): void
    {
        $current = ['key' => 'real-key', 'cc_enable' => 1, 'slider_html' => '<html>keep</html>', 'unknown' => ['keep' => true], 'log' => ['log_level' => 'debug', 'host' => '192.0.2.1', 'debug_ip' => '192.0.2.2', 'port' => 514]];
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => json_encode($current)]]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) use ($current) {
            $expected = $current;
            $expected['cc_enable'] = false;
            $expected['custom_white'] = '';
            $expected['log']['log_level'] = 'info';
            $this->assertSame($expected, json_decode($body['value'], true));

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/firewall', ['patch' => ['cc_enable' => 0, 'key' => ConfigSecrets::MASK, 'custom_white' => '', 'log' => ['log_level' => 'info']]])->assertOk()->assertJsonPath('data.key', ConfigSecrets::MASK);
    }

    public function test_invalid_whitelist_and_incomplete_resource_rules_never_write(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->twice()->with('GET', self::PATH)->andReturn(['data' => ['value' => '{}']]);
        $this->admin();
        $this->putJson('/api/admin/firewall', ['patch' => ['white_time' => 599]])->assertUnprocessable();
        $this->putJson('/api/admin/firewall', ['patch' => ['internal_rule' => [['period' => 120, 'reqs' => '']]]])->assertUnprocessable();
        $this->putJson('/api/admin/firewall', ['patch' => ['unsupported' => 1]])->assertUnprocessable();
    }

    public function test_waf_limits_validate_bytes_and_cross_field_relationships(): void
    {
        $limits = ['candidate_per_param' => 2048, 'candidate_per_request' => 4096, 'ffi_per_request' => 8192, 'json_max_leaves' => 2048, 'json_max_nodes' => 9216, 'json_max_depth' => 64, 'json_max_string_bytes' => 262144, 'json_body_bytes' => 1048576, 'multipart_max_parts' => 128, 'body_scan_bytes' => 16777216];
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->times(3)->with('GET', self::PATH)->andReturn(['data' => ['value' => json_encode(['waf_resource_limits' => $limits])]]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) {
            $this->assertSame(524288, json_decode($body['value'], true)['waf_resource_limits']['json_max_string_bytes']);

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/firewall', ['patch' => ['waf_resource_limits' => ['json_max_string_bytes' => 512]]])->assertUnprocessable();
        $this->putJson('/api/admin/firewall', ['patch' => ['waf_resource_limits' => ['candidate_per_param' => 1024]]])->assertUnprocessable();
        $this->putJson('/api/admin/firewall', ['patch' => ['waf_resource_limits' => ['json_max_string_bytes' => 524288]]])->assertOk();
    }

    public function test_blank_resource_rows_keep_native_empty_strings(): void
    {
        $rows = [['period' => 120, 'reqs' => 20], ['period' => '', 'reqs' => '']];
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => '{}']]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) use ($rows) {
            $this->assertSame($rows, json_decode($body['value'], true)['internal_rule']);

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/firewall', ['patch' => ['internal_rule' => $rows]])->assertOk();
    }

    public function test_auto_switch_uses_native_boolean_and_preserves_other_options(): void
    {
        $current = ['auto_switch' => ['enable' => true, 'qps_50x' => 10, 'qps_total' => 500, 'rule' => 2, 'seconds' => 300, 'future' => 42]];
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => json_encode($current)]]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) use ($current) {
            $current['auto_switch']['enable'] = false;
            $this->assertSame($current, json_decode($body['value'], true));

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/firewall', ['patch' => ['auto_switch' => ['enable' => 0]]])->assertOk();
    }

    public function test_override_edit_removes_selected_fields_and_preserves_unknown_fields(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs', ['type' => 'openresty_config', 'name' => 'openresty-config', 'scope_name' => 'node', 'scope_id' => 5])->andReturn(['data' => [['scope_name' => 'node', 'scope_id' => 5, 'value' => '{"cc_enable":1,"waf_enable":1,"future_option":42}']]]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) {
            $this->assertSame(['cc_enable' => 0, 'future_option' => 42], json_decode($body['value'], true));

            return $method === 'PUT' && $path === '/v1/configs/node-5-openresty_config-openresty-config';
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/firewall/overrides/node/5', ['patch' => ['cc_enable' => 0], 'remove' => ['waf_enable']])->assertOk();
    }

    public function test_override_creation_refuses_duplicates_and_global_deletion(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $query = ['type' => 'openresty_config', 'name' => 'openresty-config', 'scope_name' => 'region', 'scope_id' => 2];
        $api->shouldReceive('proxyAdminRequest')->twice()->with('GET', '/v1/configs', $query)->andReturn(['data' => []], ['data' => [['scope_name' => 'region', 'scope_id' => 2, 'value' => '{"cc_enable":0}']]]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/configs/region-2-openresty_config-openresty-config', ['value' => '{"cc_enable":0}'])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/configs/region-2-openresty_config-openresty-config')->andReturn(['code' => 0]);
        $this->admin();
        $body = ['patch' => ['cc_enable' => 0], 'creating' => true];
        $this->putJson('/api/admin/firewall/overrides/region/2', $body)->assertOk();
        $this->putJson('/api/admin/firewall/overrides/region/2', $body)->assertConflict();
        $this->deleteJson('/api/admin/firewall/overrides/global/0')->assertNotFound();
        $this->deleteJson('/api/admin/firewall/overrides/region/2')->assertOk();
    }

    public function test_image_refresh_only_queues_fixed_download_task(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) {
            $task = json_decode($body['value'], true);
            $this->assertSame('download_cc_img', $task['type']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{6}$/', $task['rnd']);
            $this->assertCount(2, $task);

            return $method === 'PUT' && $path === '/v1/configs/global-0-system-node-config';
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->postJson('/api/admin/firewall/images', ['type' => 'ignored'])->assertOk();
    }
}
