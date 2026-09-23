<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_details_and_user_search_are_admin_only_and_omit_secrets(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('listUsers')->once()->with(['search' => 'test', 'type' => 2, 'limit' => 20])->andReturn(['data' => [['id' => 6, 'name' => 'test', 'email' => 'test@example.test', 'api_key' => 'secret', 'password' => 'secret']]]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/certs/6')->andReturn(['data' => ['id' => 6, 'name' => 'Cert', 'cert' => 'PEM', 'key' => 'secret']]);
        $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/api/admin/certificate-users')->assertForbidden();
        $this->getJson('/api/admin/all-certs/6')->assertForbidden();
        $this->getJson('/api/admin/certificate-defaults/6')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/certificate-users?search=test')->assertOk()->assertExactJson(['ok' => true, 'data' => ['data' => [['id' => 6, 'name' => 'test', 'email' => 'test@example.test']]]]);
        $this->getJson('/api/admin/all-certs/6')->assertOk()->assertExactJson(['ok' => true, 'data' => ['id' => 6, 'name' => 'Cert']]);
    }

    public function test_defaults_are_scoped_to_the_selected_user_and_exclude_dns_credentials(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $config = ['id' => 10, 'uid' => 6, 'name' => 'cert_default_type', 'value' => 'lets', 'type' => 'cert', 'scope_name' => 'global'];
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-configs', ['uid' => 6, 'type' => 'cert', 'limit' => 0])->andReturn(['data' => [$config, [...$config, 'uid' => 7], [...$config, 'type' => 'site'], [...$config, 'scope_name' => 'site']]]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/dnsapis', ['uid' => 6, 'limit' => 0])->andReturn(['data' => [['id' => 8, 'uid' => 6, 'name' => 'DNS', 'type' => 'CloudFlare', 'auth' => ['CF_Key' => 'secret']], ['id' => 9, 'uid' => 7]]]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/certificate-defaults/6')->assertOk()->assertExactJson(['ok' => true, 'data' => ['configs' => [$config], 'dnsapis' => [['id' => 8, 'name' => 'DNS', 'type' => 'CloudFlare']]]]);
    }

    public function test_default_create_update_and_reset_use_native_certificate_config_contract(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $config = ['id' => 10, 'uid' => 6, 'name' => 'cert_default_type', 'value' => 'lets', 'scope_name' => 'global'];
        $service->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/user-configs', ['uid' => 6, 'type' => 'cert', 'limit' => 0])->andReturn(['data' => []], ['data' => [$config]], ['data' => [$config]]);
        $service->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/dnsapis', ['uid' => 6, 'limit' => 0])->andReturn(['data' => []]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/user-configs', ['uid' => 6, 'name' => 'cert_default_type', 'value' => 'lets', 'type' => 'cert', 'scope_name' => 'global', 'scope_id' => 0])->andReturn(['code' => 0]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/user-configs/10', ['name' => 'cert_default_type', 'value' => 'zerossl', 'type' => 'cert', 'scope_name' => 'global', 'scope_id' => 0])->andReturn(['code' => 0]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/user-configs/10')->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['lets', 'zerossl', 'system'] as $value) {
            $this->putJson('/api/admin/certificate-defaults/6', ['name' => 'cert_default_type', 'value' => $value])->assertOk();
        }
    }

    public function test_dns_default_must_belong_to_selected_user_and_can_be_cleared(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/user-configs', ['uid' => 6, 'type' => 'cert', 'limit' => 0])->andReturn(['data' => [['id' => 11, 'name' => 'dnsapi', 'value' => '8']]]);
        $service->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/dnsapis', ['uid' => 6, 'limit' => 0])->andReturn(['data' => [['id' => 8, 'uid' => 6], ['id' => 9, 'uid' => 7]]]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/user-configs/11', ['name' => 'dnsapi', 'value' => '8', 'type' => 'cert', 'scope_name' => 'global', 'scope_id' => 0])->andReturn(['code' => 0]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/user-configs/11')->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/certificate-defaults/6', ['name' => 'dnsapi', 'value' => '9'])->assertUnprocessable();
        $this->putJson('/api/admin/certificate-defaults/6', ['name' => 'dnsapi', 'value' => '8'])->assertOk();
        $this->putJson('/api/admin/certificate-defaults/6', ['name' => 'dnsapi', 'value' => ''])->assertOk();
        $this->putJson('/api/admin/certificate-defaults/6', ['name' => 'cert_default_type', 'value' => 'custom'])->assertUnprocessable();
        $this->putJson('/api/admin/certificate-defaults/6', ['name' => 'unrelated', 'value' => 'anything'])->assertUnprocessable();
    }
}
