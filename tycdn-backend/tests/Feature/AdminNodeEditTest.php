<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNodeEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_basic_edit_forwards_type_without_changing_enable_or_limits(): void
    {
        $payload = ['name' => 'edge', 'ip' => '192.0.2.1', 'type' => 'L2', 'sort' => 100, 'des' => ''];
        $this->mock(CdnflyApiService::class)->shouldReceive('updateNode')->once()->with(6, $payload)->andReturn(['code' => 0]);
        $this->actingAs($this->admin())->putJson('/api/admin/nodes/6', $payload)->assertOk();
    }

    public function test_location_supports_clearing_fields(): void
    {
        $location = ['country' => '', 'province' => '', 'city' => '', 'isp' => '', 'areacode' => ''];
        $this->mock(CdnflyApiService::class)->shouldReceive('updateNode')->once()->with(6, ['ip_location' => $location])->andReturn(['code' => 0]);
        $this->actingAs($this->admin())->putJson('/api/admin/nodes/6', ['ip_location' => $location])->assertOk();
    }

    public function test_auto_disable_uses_native_types_and_can_clear_limits(): void
    {
        $traffic = ['enable' => false, 'from_day' => 1, 'from_hour' => '12:00:00', 'traffic_total' => 500.0, 'type' => ['outbound', 'inbound'], 'excl_nic' => ''];
        $payload = ['bw_limit' => '', 'traffic_limit' => $traffic, 'disable_time' => ''];
        $this->mock(CdnflyApiService::class)->shouldReceive('updateNode')->once()->with(6, $payload)->andReturn(['code' => 0]);
        $this->actingAs($this->admin())->putJson('/api/admin/nodes/6', $payload)->assertOk();
    }

    public function test_invalid_traffic_and_disable_periods_never_reach_master(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('updateNode');
        $this->actingAs($this->admin());
        $valid = ['enable' => true, 'from_day' => 1, 'from_hour' => '12:00:00', 'traffic_total' => 500, 'type' => ['outbound'], 'excl_nic' => ''];
        foreach ([['from_day' => 32], ['from_hour' => '25:00:00'], ['traffic_total' => -1], ['type' => []], ['type' => ['invalid']]] as $change) {
            $this->putJson('/api/admin/nodes/6', ['traffic_limit' => array_replace($valid, $change)])->assertUnprocessable();
        }
        $this->putJson('/api/admin/nodes/6', ['disable_time' => '25:00:00-26:00:00'])->assertUnprocessable();
        $this->putJson('/api/admin/nodes/6', ['traffic_limit' => []])->assertUnprocessable();
    }
}
