<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPackageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_package_management_calls_cdnfly_package_endpoints(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'admin-key',
            'services.cdnfly.admin_api_secret' => 'admin-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);

        Http::fake(fn ($request) => Http::response([
            'code' => 0,
            'data' => [
                'method' => $request->method(),
                'url' => $request->url(),
                'payload' => $request->data(),
            ],
        ]));

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/packages')
            ->assertOk()
            ->assertJsonPath('data.data.method', 'GET')
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/packages');

        $this->actingAs($admin)
            ->postJson('/api/admin/packages', [
                'name' => '标准版',
                'region_id' => 1,
                'node_group_id' => 2,
                'groups' => '1',
                'month_price' => 20,
                'quarter_price' => 60,
                'year_price' => 200,
                'traffic' => 100,
                'bandwidth' => '100Mbps',
                'connection' => -1,
                'domain' => 10,
                'main_domain' => -1,
                'http_port' => 2,
                'stream_port' => 1,
                'custom_cc_rule' => 1,
                'cc_protect' => '支持',
                'ddos_protect' => '500G',
                'websocket' => 1,
                'http3' => 1,
                'l2_state' => 0,
                'cname_domain' => 1,
                'cname_hostname2' => 'vip',
                'cname_mode' => 'site',
                'buy_num_limit' => -1,
                'expire' => '2026-12-31 23:59:59',
                'owner' => '1,2',
                'enable' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.data.method', 'POST')
            ->assertJsonPath('data.data.payload.name', '标准版')
            ->assertJsonPath('data.data.payload.month_price', 20)
            ->assertJsonPath('data.data.payload.stream_port', 1)
            ->assertJsonPath('data.data.payload.custom_cc_rule', 1)
            ->assertJsonPath('data.data.payload.http3', 1)
            ->assertJsonPath('data.data.payload.l2_state', 0)
            ->assertJsonPath('data.data.payload.ddos_protect', '500G');

        $this->actingAs($admin)
            ->getJson('/api/admin/packages/101')
            ->assertOk()
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/packages/101');

        $this->actingAs($admin)
            ->putJson('/api/admin/packages/101', [
                'name' => '企业版',
                'bandwidth' => 300,
            ])
            ->assertOk()
            ->assertJsonPath('data.data.method', 'PUT')
            ->assertJsonPath('data.data.payload.bandwidth', 300);

        $this->actingAs($admin)
            ->deleteJson('/api/admin/packages/101')
            ->assertOk()
            ->assertJsonPath('data.data.method', 'DELETE');

        $this->actingAs($admin)
            ->putJson('/api/admin/packages/batch', [
                'packages' => [
                    ['id' => 101, 'enable' => 0],
                    ['id' => 102, 'month_price' => 29.9, 'sync-item' => 'month_price'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.failed_count', 0);

        Http::assertSentCount(6);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://cdnfly.example.test/v1/packages'
            && ($request->data()['name'] ?? null) === '标准版'
            && ($request->data()['month_price'] ?? null) === 20
            && ($request->data()['stream_port'] ?? null) === 1
            && ($request->data()['http3'] ?? null) === 1
            && ($request->data()['l2_state'] ?? null) === 0
            && ($request->data()['cc_protect'] ?? null) === '支持'
            && ($request->data()['ddos_protect'] ?? null) === '500G'
            && ! array_key_exists('user_id', $request->data()));
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://cdnfly.example.test/v1/packages'
            && ($request->data()[0]['id'] ?? null) === 101
            && ($request->data()[0]['enable'] ?? null) === 0
            && ($request->data()[1]['month_price'] ?? null) === 29.9
            && ($request->data()[1]['sync-item'] ?? null) === 'month_price');
    }

    public function test_admin_package_options_are_loaded_without_sensitive_config_data(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'admin-key',
            'services.cdnfly.admin_api_secret' => 'admin-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);

        Http::fake([
            'https://cdnfly.example.test/v1/regions*' => Http::response([
                'code' => 0,
                'data' => [
                    ['id' => 1, 'name' => '华东'],
                ],
            ]),
            'https://cdnfly.example.test/v1/node-groups*' => Http::response([
                'code' => 0,
                'data' => [
                    ['id' => 2, 'name' => '默认线路组'],
                ],
            ]),
            'https://cdnfly.example.test/v1/package-groups*' => Http::response([
                'code' => 0,
                'data' => [
                    ['id' => 3, 'name' => '默认套餐组'],
                ],
            ]),
            'https://cdnfly.example.test/v1/cname-domains*' => Http::response([
                'code' => 0,
                'data' => [
                    ['id' => 4, 'domain' => 'cdn.example.com', 'token' => 'secret'],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/package-options')
            ->assertOk()
            ->assertJsonPath('data.regions.0.name', '华东')
            ->assertJsonPath('data.node_groups.0.name', '默认线路组')
            ->assertJsonPath('data.package_groups.0.name', '默认套餐组')
            ->assertJsonPath('data.cname_domains.0.id', 4)
            ->assertJsonPath('data.cname_domains.0.name', 'cdn.example.com')
            ->assertJsonMissing(['token' => 'secret']);
    }

    public function test_admin_package_options_can_use_configured_cname_domains(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'admin-key',
            'services.cdnfly.admin_api_secret' => 'admin-secret',
            'services.cdnfly.cname_domain_options' => '1:cdn-a.example.com,2=cdn-b.example.com',
        ]);

        Http::fake([
            'https://cdnfly.example.test/v1/regions*' => Http::response(['code' => 0, 'data' => []]),
            'https://cdnfly.example.test/v1/node-groups*' => Http::response(['code' => 0, 'data' => []]),
            'https://cdnfly.example.test/v1/package-groups*' => Http::response(['code' => 0, 'data' => []]),
            'https://cdnfly.example.test/v1/cname-domains*' => Http::response(['message' => 'not found'], 404),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/package-options')
            ->assertOk()
            ->assertJsonPath('data.cname_domains.0.id', 1)
            ->assertJsonPath('data.cname_domains.0.name', 'cdn-a.example.com')
            ->assertJsonPath('data.cname_domains.1.id', 2)
            ->assertJsonPath('data.cname_domains.1.name', 'cdn-b.example.com');
    }

    public function test_admin_package_management_rejects_sensitive_fields(): void
    {
        Http::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/packages', [
                'name' => '标准版',
                'user_id' => 10,
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->putJson('/api/admin/packages/101', [
                'config' => [
                    'api_secret' => 'secret',
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->putJson('/api/admin/packages/batch', [
                'packages' => [
                    ['id' => 101, 'role' => 'admin'],
                ],
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_non_admin_cannot_manage_packages(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/admin/packages')
            ->assertForbidden();
    }

    public function test_admin_can_visit_console_package_management_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/console/admin/packages')
            ->assertOk();
    }
}
