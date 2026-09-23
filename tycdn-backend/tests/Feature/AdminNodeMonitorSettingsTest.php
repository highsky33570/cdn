<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNodeMonitorSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_logs_forward_the_native_filters_and_pagination(): void
    {
        $query = ['page' => '2', 'limit' => '10', 'type' => '备用IP', 'action' => '禁用', 'ip' => '192.0.2.1', 'node_group_id' => '3', 'node_id' => '6', 'line_id' => '9'];
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()
            ->with('GET', '/v1/log/ip-switch', $query)->andReturn(['code' => 0, 'count' => 0, 'data' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/workspace/ip-switch?'.http_build_query($query))->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_switch_logs_are_read_only_and_admin_only(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/api/admin/workspace/ip-switch')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin/workspace/ip-switch', [])->assertStatus(405);
    }

    public function test_monitor_autosave_preserves_templates_and_numeric_values(): void
    {
        $value = json_encode(['global_check_on' => 1, 'failed_rate' => 90, 'check_node_group' => '1,3', 'notify_method' => 'email sms', 'ip_enable_templ' => '{{node_id}} {{ip}}', 'monitor_api' => 'unchanged']);
        $payload = ['scope_name' => 'global', 'scope_id' => 0, 'type' => 'system', 'name' => 'node_monitor_config', 'value' => $value, 'enable' => 1];
        $this->mock(CdnflyApiService::class)->shouldReceive('upsertConfig')->once()->with($payload)->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/admin/configs', $payload)->assertOk();
    }
}
