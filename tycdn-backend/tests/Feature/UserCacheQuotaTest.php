<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserCacheQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'master-key',
            'services.cdnfly.admin_api_secret' => 'master-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);
        Http::preventStrayRequests();
    }

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'user', 'cdnfly_api_key' => 'customer-key', 'cdnfly_api_secret' => 'customer-secret',
        ]);
    }

    public function test_quota_and_usage_use_customer_credentials_and_ignore_scope_overrides(): void
    {
        $this->actingAs($this->customer());
        Http::fake([
            '*/v1/configs/*' => Http::response(['code' => 0, 'data' => ['value' => '2000']]),
            '*/v1/jobs*' => Http::response(['code' => 0, 'count' => 12, 'data' => []]),
        ]);
        foreach (['clean_url', 'clean_dir', 'pre_cache_url'] as $type) {
            $this->getJson('/api/cdn/cache-quota?type='.$type.'&start=2026-09-24&uid=1&scope_id=1')
                ->assertOk()->assertExactJson(['ok' => true, 'data' => ['total' => 2000, 'used' => 12]]);
        }
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('api-key', 'customer-key'));
            $this->assertTrue($request->hasHeader('api-secret', 'customer-secret'));
            $this->assertFalse($request->hasHeader('api-key', 'master-key'));
            $this->assertSame('GET', $request->method());
            $this->assertArrayNotHasKey('uid', $request->data());
            $this->assertArrayNotHasKey('scope_id', $request->data());
        }
        Http::assertSentCount(6);
    }

    public function test_quota_requires_authentication_valid_mode_date_and_customer_key(): void
    {
        $this->getJson('/api/cdn/cache-quota?type=clean_url&start=2026-09-24')->assertUnauthorized();
        $this->actingAs($this->customer());
        $this->getJson('/api/cdn/cache-quota?type=other&start=2026-09-24')->assertUnprocessable();
        $this->getJson('/api/cdn/cache-quota?type=clean_url&start=invalid')->assertUnprocessable();
        $this->postJson('/api/cdn/cache-quota', [])->assertStatus(405);
        $this->actingAs(User::factory()->create(['cdnfly_api_key' => null, 'cdnfly_api_secret' => null]));
        $this->getJson('/api/cdn/cache-quota?type=clean_url&start=2026-09-24')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_missing_quota_stays_unknown_and_upstream_failure_does_not_fall_back_to_master(): void
    {
        $this->actingAs($this->customer());
        Http::fake(['*' => Http::sequence()
            ->push(['code' => 0, 'data' => []])
            ->push(['code' => 0, 'data' => []])
            ->push(['code' => 'denied', 'msg' => 'Not permitted'], 403)]);
        $this->getJson('/api/cdn/cache-quota?type=clean_url&start=2026-09-24')
            ->assertOk()->assertExactJson(['ok' => true, 'data' => ['total' => null, 'used' => null]]);
        $this->getJson('/api/cdn/cache-quota?type=clean_url&start=2026-09-24')->assertStatus(502);
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('api-key', 'customer-key'));
        }
    }
}
