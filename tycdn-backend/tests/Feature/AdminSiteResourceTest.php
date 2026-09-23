<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSiteResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_resource_routes_require_admin_and_restrict_resources_and_methods(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/api/admin/site-resources/site-groups')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/site-resources/users')->assertNotFound();
        $this->putJson('/api/admin/site-resources/domains/1', [])->assertStatus(405);
        $this->deleteJson('/api/admin/site-resources/site-groups')->assertStatus(405);
        $this->getJson('/api/admin/site-resources/cname-check')->assertStatus(405);
    }

    public function test_default_settings_are_scoped_to_sites_and_preserve_blank_values(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-configs', ['page' => '2', 'limit' => '10', 'type' => 'site'])->andReturn(['code' => 0, 'data' => [], 'count' => 0]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/user-configs/9', ['type' => 'site', 'value' => '', 'enable' => 0])->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/site-resources/user-configs?page=2&limit=10&type=system')->assertOk();
        $this->putJson('/api/admin/site-resources/user-configs/9', ['type' => 'site', 'value' => '', 'enable' => 0])->assertOk();
    }

    public function test_admin_creates_group_for_selected_owner_and_strips_extra_fields(): void
    {
        $payload = ['uid' => 6, 'name' => 'Production', 'des' => 'Sites'];
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/site-groups', $payload)->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin/site-resources/site-groups', $payload + ['admin' => 1])->assertOk();
        $this->postJson('/api/admin/site-resources/site-groups', ['name' => 'Missing owner'])->assertUnprocessable();
    }

    public function test_dns_credentials_are_not_exposed_and_unmodified_credentials_are_omitted(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/dnsapis', [])->andReturn(['code' => 0, 'data' => [['id' => 4, 'name' => 'DNS', 'auth' => ['CF_Key' => 'hidden']]]]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/dnsapis/4', ['name' => 'Renamed', 'des' => 'Note', 'type' => 'CloudFlare'])->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/site-resources/dnsapis')->assertOk()->assertJsonMissingPath('data.data.0.auth');
        $this->putJson('/api/admin/site-resources/dnsapis/4', ['name' => 'Renamed', 'des' => 'Note', 'type' => 'CloudFlare'])->assertOk();
    }

    public function test_domain_check_preserves_object_keys_and_sync_uses_only_selected_ids(): void
    {
        $check = ['domain_9' => ['domain' => '*.example.com', 'cname' => 'test.cdn.example']];
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/cname-check', $check)->andReturn(['code' => 0, 'data' => ['domain_9' => true]]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/domains', [['id' => 9]])->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin/site-resources/cname-check', $check)->assertOk()->assertJsonPath('data.data.domain_9', true);
        $this->postJson('/api/admin/site-resources/domains', [['id' => 9]])->assertOk();
        $this->postJson('/api/admin/site-resources/domains', [])->assertUnprocessable();
        $this->postJson('/api/admin/site-resources/domains', [['id' => 9, 'enable' => 1]])->assertUnprocessable();
    }
}
