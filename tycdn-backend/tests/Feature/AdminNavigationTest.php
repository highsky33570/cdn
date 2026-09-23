<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'line-groups' => 'admin-line-groups', 'cache/jobs' => 'admin-cache-jobs',
        'security/cc' => 'admin-security-cc', 'security/waf' => 'admin-security-waf',
        'sold-packages' => 'admin-sold-packages', 'finance/recharge' => 'admin-finance-recharge',
        'finance/orders' => 'admin-finance-orders', 'finance/recharge-count' => 'admin-finance-recharge-count',
        'message-query' => 'admin-message-query',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        config(['services.cdnfly.base_url' => 'https://cdnfly.example.test', 'services.cdnfly.admin_api_key' => 'master-key', 'services.cdnfly.admin_api_secret' => 'master-secret', 'services.cdnfly.outbound_enabled' => true]);
        Http::preventStrayRequests();
    }

    public function test_new_menu_destinations_resolve_to_the_intended_modules(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (self::PAGES as $path => $module) {
            $this->get('/console/admin/'.$path)->assertOk()->assertInertia(fn (Assert $page) => $page->component('console/Module')->where('moduleKey', $module));
        }
    }

    public function test_menu_pages_and_resources_keep_admin_authorization(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach (self::PAGES as $path => $module) {
            $this->get('/console/admin/'.$path)->assertForbidden();
        }
        foreach (['cache-jobs', 'master-orders', 'recharge-count', 'message-query'] as $resource) {
            $this->getJson('/api/admin/workspace/'.$resource)->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_menu_reads_forward_native_filters_and_master_credentials(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'count' => 1, 'data' => [['id' => 1, 'amount' => 5000, 'sum' => 50]]])]);
        foreach ([
            ['master-orders', '/v1/orders', ['type' => '充值', 'uid' => '0', 'page' => '1', 'limit' => '10']],
            ['recharge-count', '/v1/order/count', ['type' => '充值', 'state' => '已付款', 'group_by' => 'month', 'start' => '2026-08-23', 'end' => '2026-09-24']],
            ['message-query', '/v1/messages', ['receive' => '0', 'type' => 'cert-expire', 'site_id' => '1']],
            ['cache-jobs', '/v1/jobs', ['type' => 'clean_url']],
        ] as [$resource, $upstream, $query]) {
            $this->getJson('/api/admin/workspace/'.$resource.'?'.http_build_query($query))->assertOk()->assertJsonPath('data.data.0.amount', 5000);
            Http::assertSent(fn (Request $request) => str_contains($request->url(), $upstream) && $request->hasHeader('api-key', 'master-key') && $request->data() === $query);
        }
    }

    public function test_admin_cache_submits_only_supported_cache_jobs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*/v1/jobs' => Http::response(['code' => 0, 'data' => 1])]);
        $jobs = [['type' => 'clean_url', 'data' => ['url' => 'https://example.test/a']], ['type' => 'pre_cache_url', 'data' => ['url' => 'https://example.test/b']]];
        $this->postJson('/api/admin/workspace/cache-jobs', $jobs)->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === $jobs && $request->hasHeader('api-key', 'master-key'));
        foreach ([[], [['type' => 'shell', 'data' => ['url' => 'test']]], [['type' => 'clean_url', 'data' => ['url' => 'test', 'command' => 'test']]]] as $invalid) {
            $this->postJson('/api/admin/workspace/cache-jobs', $invalid)->assertUnprocessable();
        }
        Http::assertSentCount(1);
    }

    public function test_financial_and_message_menu_resources_are_read_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['master-orders', 'recharge-count', 'message-query'] as $resource) {
            $this->postJson('/api/admin/workspace/'.$resource, [])->assertStatus(405);
        }
        Http::assertNothingSent();
    }
}
