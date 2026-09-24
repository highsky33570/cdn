<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackageUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_workspace_routes_require_an_administrator(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        foreach ([['GET', 'sold'], ['GET', 'sold/4'], ['PUT', 'sold/4'], ['DELETE', 'sold/4'], ['GET', '4'], ['PUT', 'status'], ['DELETE', 'batch'], ['POST', 'assign']] as [$method, $path]) {
            $this->json($method, '/api/admin/package-upgrades/'.$path)->assertForbidden();
        }
    }

    public function test_native_detail_and_sold_record_paths_and_filters_are_preserved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/package-ups/3', [])->andReturn(['data' => ['id' => 3, 'price' => 5, 'type' => 'domain']]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-package-ups', ['page' => '2', 'limit' => '10', 'uid' => '7', 'package_up' => '3'])->andReturn(['data' => [], 'count' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-package-ups/4', [])->andReturn(['data' => ['id' => 4, 'amount' => 2]]);
        $this->getJson('/api/admin/package-upgrades/3')->assertOk()->assertJsonPath('data.data.price', 5);
        $this->getJson('/api/admin/package-upgrades/sold?page=2&limit=10&uid=7&package_up=3&ignored=x')->assertOk()->assertJsonPath('data.count', 0);
        $this->getJson('/api/admin/package-upgrades/sold/4')->assertOk()->assertJsonPath('data.data.amount', 2);
    }

    public function test_batch_actions_and_sold_mutations_use_native_payloads(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/package-ups', [['id' => 3, 'enable' => 0], ['id' => 5, 'enable' => 0]])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/package-ups/3,5', [])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/user-package-ups/4', ['amount' => 3])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/user-package-ups/4', [])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/user-package-ups', ['uid' => 7, 'package_up' => 3, 'user_package' => 8, 'amount' => 2])->andReturn(['code' => 0]);
        $this->putJson('/api/admin/package-upgrades/status', ['ids' => [3, 5], 'enable' => 0, 'price' => 99])->assertOk();
        $this->deleteJson('/api/admin/package-upgrades/batch', ['ids' => [3, 5]])->assertOk();
        $this->putJson('/api/admin/package-upgrades/sold/4', ['amount' => 3, 'uid' => 999])->assertOk();
        $this->deleteJson('/api/admin/package-upgrades/sold/4')->assertOk();
        $this->postJson('/api/admin/package-upgrades/assign', ['uid' => 7, 'package_up' => 3, 'user_package' => 8, 'amount' => 2, 'enable' => 0])->assertOk();
    }

    public function test_invalid_batches_and_quantities_do_not_reach_the_master(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->putJson('/api/admin/package-upgrades/status', ['ids' => [], 'enable' => 1])->assertUnprocessable();
        $this->putJson('/api/admin/package-upgrades/status', ['ids' => [1, 1], 'enable' => 1])->assertUnprocessable();
        $this->putJson('/api/admin/package-upgrades/status', ['ids' => range(1, 51), 'enable' => 1])->assertUnprocessable();
        $this->deleteJson('/api/admin/package-upgrades/batch', ['ids' => ['1/2']])->assertUnprocessable();
        $this->putJson('/api/admin/package-upgrades/sold/4', ['amount' => 1.5])->assertUnprocessable();
        $this->postJson('/api/admin/package-upgrades/assign', ['uid' => 7, 'package_up' => 3, 'user_package' => 8, 'amount' => 0])->assertUnprocessable();
        $this->getJson('/api/admin/package-upgrades/sold?uid=bad')->assertUnprocessable();
    }

    public function test_upstream_failures_are_visible(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/user-package-ups', [])->andThrow(new \RuntimeException('Unavailable'));
        $this->getJson('/api/admin/package-upgrades/sold')->assertStatus(500)->assertJsonPath('ok', false);
    }
}
