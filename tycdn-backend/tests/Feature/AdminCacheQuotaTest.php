<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCacheQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quota_requires_admin_and_restricts_config_names(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/api/admin/cache-quota?type=clean_url&start=2026-09-24')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/cache-quota?type=dns_config&start=2026-09-24')->assertUnprocessable();
        $this->getJson('/api/admin/cache-quota?type=clean_url&start=invalid')->assertUnprocessable();
        $this->postJson('/api/admin/cache-quota', ['type' => 'clean_url'])->assertStatus(405);
    }

    public function test_quota_uses_matching_native_config_and_today_job_count(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['clean_url' => 2000, 'clean_dir' => 500, 'pre_cache_url' => 2000] as $type => $limit) {
            $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs/global-0-site-'.$type)->andReturn(['data' => ['value' => (string) $limit]]);
            $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/jobs', ['type' => $type, 'start' => '2026-09-24', 'limit' => 1])->andReturn(['count' => 12, 'data' => [['id' => 1]]]);
            $this->getJson('/api/admin/cache-quota?type='.$type.'&start=2026-09-24')->assertOk()->assertExactJson(['ok' => true, 'data' => ['total' => $limit, 'used' => 12]]);
        }
    }

    public function test_missing_quota_is_reported_as_unknown_not_invented(): void
    {
        $service = $this->mock(CdnflyApiService::class);
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs/global-0-site-clean_url')->andReturn(['data' => []]);
        $service->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/jobs', ['type' => 'clean_url', 'start' => '2026-09-24', 'limit' => 1])->andReturn(['data' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/cache-quota?type=clean_url&start=2026-09-24')->assertOk()->assertExactJson(['ok' => true, 'data' => ['total' => null, 'used' => null]]);
    }
}
