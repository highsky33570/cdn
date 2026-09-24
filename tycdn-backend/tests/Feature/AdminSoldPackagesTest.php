<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSoldPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_routes_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->getJson('/api/admin/sold-packages/5')->assertForbidden();
        $this->getJson('/api/admin/sold-packages/5/usage')->assertForbidden();
        $this->postJson('/api/admin/sold-packages/5/upgrades', ['package_up' => 2, 'amount' => 1])->assertForbidden();
    }

    public function test_detail_quote_and_usage_use_native_read_paths(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-packages/5', [])->andReturn(['data' => ['id' => 5, 'traffic' => 300]]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-packages/5', ['to_package' => '4'])->andReturn(['data' => ['diff_price' => 12]]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-package/5/usage')->andReturn(['data' => ['traffic_usage' => 32]]);
        $this->getJson('/api/admin/sold-packages/5?ignored=x')->assertOk()->assertJsonPath('data.data.traffic', 300);
        $this->getJson('/api/admin/sold-packages/5?to_package=4')->assertOk()->assertJsonPath('data.data.diff_price', 12);
        $this->getJson('/api/admin/sold-packages/5/usage')->assertOk()->assertJsonPath('data.data.traffic_usage', 32);
    }

    public function test_upgrade_payload_is_validated_and_restricted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/user-package/5/upgrades', ['package_up' => 3, 'amount' => 2])->andReturn(['code' => 0]);
        $this->postJson('/api/admin/sold-packages/5/upgrades', ['package_up' => 3, 'amount' => 2, 'uid' => 999])->assertOk();
        $this->postJson('/api/admin/sold-packages/5/upgrades', ['package_up' => 3, 'amount' => 0])->assertUnprocessable();
        $this->postJson('/api/admin/sold-packages/5/upgrades', ['package_up' => 3, 'amount' => 1.5])->assertUnprocessable();
        $this->getJson('/api/admin/sold-packages/5?to_package=bad')->assertUnprocessable();
    }

    public function test_failed_usage_read_is_not_reported_as_zero_usage(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-package/5/usage')->andThrow(new \RuntimeException('Unavailable'));
        $this->getJson('/api/admin/sold-packages/5/usage')->assertStatus(500)->assertJsonPath('ok', false);
    }
}
