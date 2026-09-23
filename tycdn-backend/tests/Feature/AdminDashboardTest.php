<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cdnfly.base_url' => 'https://cdnfly.example.test', 'services.cdnfly.admin_api_key' => 'master-key', 'services.cdnfly.admin_api_secret' => 'master-secret', 'services.cdnfly.outbound_enabled' => true]);
        Http::preventStrayRequests();
    }

    public function test_operating_charts_forward_master_date_ranges_and_paid_recharge_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['cdnfly.example.test/*' => Http::response(['code' => 0, 'data' => [['time' => '2026-09-19', 'count' => 1, 'sum' => '50.00']]])]);
        foreach (['new-user-count' => '/v1/new-user/count', 'package-sold-count' => '/v1/package-sold/count', 'recharge-count' => '/v1/order/count'] as $resource => $path) {
            $query = ['start' => '2026-09-16', 'end' => '2026-09-24'];
            if ($resource === 'recharge-count') {
                $query += ['limit' => '0', 'state' => '已付款', 'type' => '充值', 'group_by' => 'day'];
            }
            $this->getJson('/api/admin/workspace/'.$resource.'?'.http_build_query($query))->assertOk()->assertJsonPath('data.data.0.sum', '50.00');
            Http::assertSent(fn (Request $request) => str_contains($request->url(), $path) && $request->data() === $query);
        }
    }

    public function test_dashboard_account_only_returns_master_identity(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*/v1/user' => Http::response(['code' => 0, 'data' => ['id' => 1, 'name' => 'master-admin', 'email' => 'private@example.test', 'cert_no' => 'private', 'balance' => 300]])]);
        $this->getJson('/api/admin/workspace/master-account?uid=9')->assertOk()->assertExactJson(['ok' => true, 'data' => ['data' => ['id' => 1, 'name' => 'master-admin']]]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdnfly.example.test/v1/user' && $request->data() === []);
    }

    public function test_agent_and_license_actions_use_exact_post_routes_without_forwarding_extra_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['cdnfly.example.test/*' => Http::response(['code' => 0, 'data' => []])]);
        foreach (['agent-check' => '/v1/maintain/agent-check', 'license' => '/v1/common/auth'] as $resource => $path) {
            $this->postJson('/api/admin/workspace/'.$resource, ['unexpected' => 'discard'])->assertOk();
            Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), $path) && $request->data() === []);
            $this->postJson('/api/admin/workspace/'.$resource.'/123')->assertNotFound();
        }
        $this->getJson('/api/admin/workspace/agent-check')->assertStatus(405);
        $this->postJson('/api/admin/workspace/master-account')->assertStatus(405);
    }

    public function test_user_accounts_cannot_query_master_dashboard_or_trigger_checks(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach (['master-account', 'new-user-count', 'package-sold-count'] as $resource) {
            $this->getJson('/api/admin/workspace/'.$resource)->assertForbidden();
        }
        $this->postJson('/api/admin/workspace/agent-check')->assertForbidden();
        $this->postJson('/api/admin/workspace/license')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_upstream_failure_is_not_reported_as_healthy(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*/v1/admin/overview' => Http::response(['code' => 1, 'msg' => 'master unavailable'])]);
        $this->getJson('/api/admin/workspace/overview')->assertStatus(500)->assertJsonPath('ok', false);
    }
}
