<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStreamWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_require_admin_and_default_lists_force_stream_type(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/api/admin/stream-defaults')->assertForbidden();
        $this->getJson('/api/admin/streams/4')->assertForbidden();
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-configs', ['type' => 'stream', 'uid' => '6', 'limit' => '10'])->andReturn(['count' => 0, 'data' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/stream-defaults?type=system&uid=6&limit=10')->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_group_create_requires_owner_and_strips_unrelated_fields(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('adminCreateStreamGroup')->once()->with(['uid' => 6, 'name' => 'Forwarding', 'des' => 'Test'])->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/stream-groups', ['name' => 'Missing owner'])->assertUnprocessable();
        $this->postJson('/api/admin/stream-groups', ['uid' => 6, 'name' => 'Forwarding', 'des' => 'Test', 'unrelated' => 'ignored'])->assertOk();
    }

    public function test_default_create_validates_values_and_group_ownership(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['uid' => 6, 'name' => 'proxy_protocol', 'value' => '0', 'scope_name' => 'global', 'scope_id' => 0];
        $this->postJson('/api/admin/stream-defaults', array_replace($payload, ['value' => 'invalid']))->assertUnprocessable();
        $this->postJson('/api/admin/stream-defaults', array_replace($payload, ['name' => 'system_config']))->assertUnprocessable();
        $service->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/user-configs', $payload + ['type' => 'stream'])->andReturn(['code' => 0]);
        $this->postJson('/api/admin/stream-defaults', $payload + ['type' => 'site'])->assertOk();
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/stream-groups/9')->andReturn(['data' => ['id' => 9, 'uid' => 7]]);
        $this->postJson('/api/admin/stream-defaults', array_replace($payload, ['scope_name' => 'group', 'scope_id' => 9]))->assertUnprocessable();
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/stream-groups/10')->andReturn(['data' => ['id' => 10, 'uid' => 6]]);
        $groupPayload = array_replace($payload, ['scope_name' => 'group', 'scope_id' => 10]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/user-configs', $groupPayload + ['type' => 'stream'])->andReturn(['code' => 0]);
        $this->postJson('/api/admin/stream-defaults', $groupPayload)->assertOk();
    }

    public function test_default_edit_and_delete_cannot_touch_other_config_types(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/user-configs', ['type' => 'stream', 'limit' => 0])->andReturn(['data' => [['id' => 3, 'uid' => 6, 'type' => 'stream']]]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson('/api/admin/stream-defaults/4')->assertNotFound();
        $payload = ['uid' => 6, 'name' => 'balance_way', 'value' => 'ip_hash', 'scope_name' => 'global', 'scope_id' => 0];
        $service->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/user-configs/3', $payload + ['type' => 'stream'])->andReturn(['code' => 0]);
        $this->putJson('/api/admin/stream-defaults/3', $payload)->assertOk();
        $service->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/user-configs/3', [])->andReturn(['code' => 0]);
        $this->deleteJson('/api/admin/stream-defaults/3')->assertOk();
    }

    public function test_stream_detail_preserves_all_listeners_and_backends(): void
    {
        $detail = ['data' => ['id' => 5, 'listen' => '[{"protocol":"tcp","port":80},{"protocol":"udp","port":53}]', 'backend' => '[{"addr":"192.0.2.1","weight":2,"state":"up"},{"addr":"192.0.2.2","weight":1,"state":"backup"}]']];
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/streams/5')->andReturn($detail);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/streams/5')->assertOk()->assertExactJson(['ok' => true, 'data' => $detail]);
    }
}
