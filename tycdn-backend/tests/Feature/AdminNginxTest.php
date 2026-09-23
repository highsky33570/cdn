<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNginxTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/v1/configs/global-0-nginx_config-nginx-config-file';

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_routes_require_admin(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/nginx')->assertForbidden();
        $this->putJson('/api/admin/nginx', ['patch' => ['worker_connections' => '1024']])->assertForbidden();
        $this->getJson('/api/admin/nginx/overrides')->assertForbidden();
        $this->putJson('/api/admin/nginx/overrides/node/1', ['patch' => ['worker_connections' => '1024']])->assertForbidden();
        $this->deleteJson('/api/admin/nginx/overrides/node/1')->assertForbidden();
    }

    public function test_read_preserves_server_tokens_switch_while_masking_actual_secrets(): void
    {
        $config = ['worker_processes' => 'auto', 'http' => ['server_tokens' => 'off', 'future_api_secret' => 'secret']];
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => json_encode($config)]]);
        $this->admin();
        $this->getJson('/api/admin/nginx')->assertOk()->assertJsonPath('data.http.server_tokens', 'off')->assertJsonPath('data.http.future_api_secret', ConfigSecrets::MASK);
    }

    public function test_override_list_forces_nginx_type_and_scopes(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs', ['page' => '2', 'limit' => '10', 'type' => 'nginx_config', 'name' => 'nginx-config-file', 'scope_name' => 'region,node'])->andReturn(['count' => 11, 'data' => [['scope_name' => 'node', 'scope_id' => 5, 'value' => '{"http":{"server_tokens":"on"}}']]]);
        $this->admin();
        $response = $this->getJson('/api/admin/nginx/overrides?page=2&limit=10&type=system&scope_name=global')->assertOk()->assertJsonPath('data.count', 11);
        $this->assertSame('on', json_decode($response->json('data.data.0.value'), true)['http']['server_tokens']);
    }

    public function test_global_leaf_patches_preserve_other_contexts_and_unknown_values(): void
    {
        $current = ['worker_connections' => 51200, 'http' => ['proxy_connect_timeout' => '60s', 'client_body_buffer_size' => '16', 'future_option' => ['keep' => 1]], 'stream' => ['proxy_connect_timeout' => '60s', 'proxy_timeout' => '10m'], 'future_secret' => 'secret'];
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => json_encode($current)]]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) use ($current) {
            $current['http']['proxy_connect_timeout'] = '90s';
            $current['http']['client_body_buffer_size'] = '32';
            $this->assertSame($current, json_decode($body['value'], true));

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/nginx', ['patch' => ['http.proxy_connect_timeout' => '90s', 'http.client_body_buffer_size' => '32']])->assertOk();
    }

    public function test_off_zero_versions_and_stream_timeouts_keep_native_strings(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => '{"worker_processes":"auto"}']]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) {
            $this->assertSame(['worker_processes' => 'auto', 'http' => ['proxy_buffering' => 'off', 'server_addr_outgoing' => '0', 'server_tokens' => 'off', 'gzip_http_version' => '1.0', 'client_max_body_size' => '0'], 'stream' => ['proxy_timeout' => '1h 30m']], json_decode($body['value'], true));

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/nginx', ['patch' => ['http.proxy_buffering' => 'off', 'http.server_addr_outgoing' => '0', 'http.server_tokens' => 'off', 'http.gzip_http_version' => '1.0', 'http.client_max_body_size' => '0', 'stream.proxy_timeout' => '1h 30m']])->assertOk()->assertJsonPath('data.http.server_tokens', 'off');
    }

    public function test_invalid_values_and_fields_are_rejected_without_upstream_calls(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->admin();
        foreach ([['worker_connections' => '0'], ['worker_processes' => 'many'], ['http.gzip_comp_level' => '10'], ['http.proxy_buffering' => false], ['http.proxy_http_version' => '2.0'], ['http.server' => "nginx;\ninclude /other;"], ['http' => ['server' => 'x']], ['unsupported' => 'x'], ['http.server' => null]] as $patch) {
            $this->putJson('/api/admin/nginx', ['patch' => $patch])->assertUnprocessable();
        }
        $this->putJson('/api/admin/nginx', ['patch' => ['worker_processes' => 'auto'], 'remove' => ['logs_dir']])->assertUnprocessable();
    }

    public function test_failed_current_read_never_writes_replacement_defaults(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andThrow(new \RuntimeException('Unavailable'));
        $this->admin();
        $this->putJson('/api/admin/nginx', ['patch' => ['worker_processes' => 'auto']])->assertServerError();
    }

    public function test_override_updates_remove_only_selected_leaf_and_preserve_unknown_fields(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs', ['type' => 'nginx_config', 'name' => 'nginx-config-file', 'scope_name' => 'node', 'scope_id' => 5])->andReturn(['data' => [['scope_name' => 'node', 'scope_id' => 5, 'value' => '{"http":{"proxy_connect_timeout":"60s","future":"keep"},"stream":{"proxy_connect_timeout":"2s"}}']]]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) {
            $this->assertSame(['http' => ['future' => 'keep'], 'stream' => ['proxy_connect_timeout' => '3s']], json_decode($body['value'], true));

            return $method === 'PUT' && $path === '/v1/configs/node-5-nginx_config-nginx-config-file';
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/nginx/overrides/node/5', ['patch' => ['stream.proxy_connect_timeout' => '3s'], 'remove' => ['http.proxy_connect_timeout']])->assertOk();
    }

    public function test_create_duplicate_missing_edit_and_scoped_delete(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $query = ['type' => 'nginx_config', 'name' => 'nginx-config-file', 'scope_name' => 'region', 'scope_id' => 2];
        $api->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/configs', $query)->andReturn(['data' => []], ['data' => [['scope_name' => 'region', 'scope_id' => 2, 'value' => '{"worker_processes":"auto"}']]], ['data' => []]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/configs/region-2-nginx_config-nginx-config-file', ['value' => '{"worker_processes":"auto"}'])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/configs/region-2-nginx_config-nginx-config-file')->andReturn(['code' => 0]);
        $this->admin();
        $body = ['patch' => ['worker_processes' => 'auto'], 'creating' => true];
        $this->putJson('/api/admin/nginx/overrides/region/2', $body)->assertOk();
        $this->putJson('/api/admin/nginx/overrides/region/2', $body)->assertConflict();
        $this->putJson('/api/admin/nginx/overrides/region/2', ['patch' => ['worker_processes' => 'auto']])->assertNotFound();
        $this->deleteJson('/api/admin/nginx/overrides/global/0')->assertNotFound();
        $this->deleteJson('/api/admin/nginx/overrides/region/2')->assertOk();
    }
}
