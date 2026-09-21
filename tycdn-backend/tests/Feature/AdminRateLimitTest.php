<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_bursts_are_counted_once_and_do_not_block_site_saves(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('proxyAdminRequest')->times(90)->andReturn(['code' => 0, 'data' => []]);
        $cdnfly->shouldReceive('updateAdminSite')->once()->with(9, ['enable' => 1])->andReturn(['code' => 0]);

        for ($i = 0; $i < 90; $i++) {
            $this->getJson('/api/admin/workspace/site-realtime?type=qps&start=2026-09-21+14:42:00&end=2026-09-21+15:42:00')
                ->assertOk()->assertHeader('X-RateLimit-Limit', '600')
                ->assertHeader('X-RateLimit-Remaining', (string) (599 - $i));
        }

        $this->putJson('/api/admin/sites/9', ['enable' => 1])->assertOk()
            ->assertHeader('X-RateLimit-Limit', '20')->assertHeader('X-RateLimit-Remaining', '19');
    }

    public function test_read_limit_returns_retry_headers_and_does_not_exhaust_writes(): void
    {
        config(['rate_limits.admin_reads_per_minute' => 2]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('proxyAdminRequest')->with('GET', '/v1/monitor/site/realtime', [])->twice()->andReturn(['data' => []]);
        $cdnfly->shouldReceive('proxyAdminRequest')->with('PUT', '/v1/tasks/12', ['enable' => 0])->once()->andReturn(['code' => 0]);

        $this->getJson('/api/admin/workspace/site-realtime')->assertOk();
        $this->getJson('/api/admin/workspace/site-realtime')->assertOk();
        $limited = $this->getJson('/api/admin/workspace/site-realtime')->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '2')->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Reset');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $this->putJson('/api/admin/workspace/tasks/12', ['enable' => 0])->assertOk();
    }

    public function test_write_limits_are_per_action_not_per_record_and_leave_reads_available(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateAdminSite')->times(20)->andReturn(['code' => 0]);
        $cdnfly->shouldReceive('upsertConfig')->once()->andReturn(['code' => 0]);
        $cdnfly->shouldReceive('proxyAdminRequest')->once()->andReturn(['data' => []]);

        for ($id = 1; $id <= 20; $id++) {
            $this->putJson('/api/admin/sites/'.$id, ['enable' => 1])->assertOk();
        }
        $this->putJson('/api/admin/sites/21', ['enable' => 1])->assertStatus(429);
        $this->putJson('/api/admin/configs', ['name' => 'system_info', 'type' => 'system', 'value' => '{}'])->assertOk();
        $this->getJson('/api/admin/workspace/site-realtime')->assertOk()->assertHeader('X-RateLimit-Remaining', '599');
    }

    public function test_accounts_and_workspace_resources_have_separate_counters(): void
    {
        config(['rate_limits.admin_reads_per_minute' => 1]);
        $first = User::factory()->create(['role' => 'admin']);
        $second = User::factory()->create(['role' => 'admin']);
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->times(33)->andReturn(['data' => []]);

        $this->actingAs($first)->getJson('/api/admin/workspace/overview')->assertOk();
        $this->getJson('/api/admin/workspace/site-realtime')->assertStatus(429);
        $this->actingAs($second)->getJson('/api/admin/workspace/site-realtime')->assertOk();

        for ($id = 1; $id <= 30; $id++) {
            $this->putJson('/api/admin/workspace/tasks/'.$id, ['enable' => 0])->assertOk();
        }
        $this->putJson('/api/admin/workspace/tasks/31', ['enable' => 0])->assertStatus(429);
        $this->postJson('/api/admin/workspace/l2-configs', ['name' => 'test'])->assertOk();
    }

    public function test_global_write_budget_is_independent_of_the_read_budget(): void
    {
        config(['rate_limits.admin_writes_per_minute' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->twice()->andReturn(['data' => []]);

        $this->putJson('/api/admin/workspace/tasks/1', ['enable' => 0])->assertOk();
        $this->postJson('/api/admin/workspace/l2-configs', ['name' => 'test'])->assertStatus(429);
        $this->getJson('/api/admin/workspace/site-realtime')->assertOk();
    }

    public function test_mixed_read_write_routes_do_not_charge_the_write_budget_for_reads(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->times(25)->andReturn(['data' => []]);

        for ($i = 0; $i < 25; $i++) {
            $this->getJson('/api/admin/sites/9/waf-rules')->assertOk()->assertHeader('X-RateLimit-Limit', '600');
        }
    }

    public function test_an_existing_config_cache_without_the_new_keys_uses_the_defaults(): void
    {
        config(['rate_limits' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->andReturn(['data' => []]);

        $this->getJson('/api/admin/workspace/site-realtime')->assertOk()->assertHeader('X-RateLimit-Limit', '600');
    }

    public function test_read_policy_still_requires_an_authenticated_administrator(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->getJson('/api/admin/workspace/site-realtime')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/workspace/site-realtime')->assertForbidden();
    }
}
