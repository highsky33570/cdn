<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminConsoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_returns_local_and_cdnfly_summary(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'admin-key',
            'services.cdnfly.admin_api_secret' => 'admin-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);

        Http::fake([
            'https://cdnfly.example.test/v1/users*' => Http::response([
                'code' => 0,
                'data' => ['total' => 7, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/packages*' => Http::response([
                'code' => 0,
                'data' => ['total' => 3, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/user-packages*' => Http::response([
                'code' => 0,
                'data' => ['total' => 5, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/nodes*' => Http::response([
                'code' => 0,
                'data' => ['total' => 4, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/pending-nodes*' => Http::response([
                'code' => 0,
                'data' => ['total' => 2, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/sites*' => Http::response([
                'code' => 0,
                'data' => ['total' => 11, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/streams*' => Http::response([
                'code' => 0,
                'data' => ['total' => 12, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/certs*' => Http::response([
                'code' => 0,
                'data' => ['total' => 13, 'data' => []],
            ]),
            // v6 renamed /v1/acls to /v1/waf-rules
            'https://cdnfly.example.test/v1/waf-rules*' => Http::response([
                'code' => 0,
                'data' => ['total' => 14, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/dnsapis*' => Http::response([
                'code' => 0,
                'data' => ['total' => 15, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/regions*' => Http::response([
                'code' => 0,
                'data' => ['total' => 16, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/node-groups*' => Http::response([
                'code' => 0,
                'data' => ['total' => 17, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/package-groups*' => Http::response([
                'code' => 0,
                'data' => ['total' => 18, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/cname-domains*' => Http::response([
                'code' => 0,
                'data' => ['total' => 19, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/stream-groups*' => Http::response([
                'code' => 0,
                'data' => ['total' => 20, 'data' => []],
            ]),
            'https://cdnfly.example.test/v1/lines*' => Http::response([
                'code' => 0,
                'data' => ['total' => 21, 'data' => []],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'cdnfly_user_id' => 1]);
        User::factory()->create(['role' => 'user']);
        Product::query()->create([
            'name' => '标准版',
            'slug' => 'standard',
            'price_monthly' => 20,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        Order::query()->create([
            'order_no' => '202604280001',
            'user_id' => $admin->id,
            'amount_usdt' => 20,
            'pay_address' => 'T0000000000000000000000000000000000',
            'status' => 'pending',
            'expire_at' => now()->addMinutes(10),
        ]);
        ServiceInstance::query()->create([
            'user_id' => $admin->id,
            'status' => 'failed',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.metrics.users_total', 2)
            ->assertJsonPath('data.metrics.admins_total', 1)
            ->assertJsonPath('data.metrics.orders_pending', 1)
            ->assertJsonPath('data.metrics.services_failed', 1)
            ->assertJsonPath('data.metrics.active_products', 1)
            ->assertJsonPath('data.cdnfly.users_total', 7)
            ->assertJsonPath('data.cdnfly.packages_total', 3)
            ->assertJsonPath('data.cdnfly.user_packages_total', 5)
            ->assertJsonPath('data.cdnfly.nodes_total', 4)
            ->assertJsonPath('data.cdnfly.pending_nodes_total', 2)
            ->assertJsonPath('data.cdnfly.sites_total', 11)
            ->assertJsonPath('data.cdnfly.streams_total', 12)
            ->assertJsonPath('data.cdnfly.certs_total', 13)
            ->assertJsonPath('data.cdnfly.acls_total', 14)
            ->assertJsonPath('data.cdnfly.dns_apis_total', 15)
            ->assertJsonPath('data.cdnfly.regions_total', 16)
            ->assertJsonPath('data.cdnfly.node_groups_total', 17)
            ->assertJsonPath('data.cdnfly.package_groups_total', 18)
            ->assertJsonPath('data.cdnfly.cname_domains_total', 19)
            ->assertJsonPath('data.cdnfly.stream_groups_total', 20)
            ->assertJsonPath('data.cdnfly.lines_total', 21);
    }

    public function test_admin_can_list_and_update_users_without_exposing_secrets(): void
    {
        Http::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'role' => 'user',
            'cdnfly_user_id' => 1001,
            'cdnfly_api_key' => 'secret-key',
            'cdnfly_api_secret' => 'secret-value',
        ]);

        $listResponse = $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('data.data.0.has_api_key', true);

        $this->assertArrayNotHasKey('cdnfly_api_key', $listResponse->json('data.data.0'));
        $this->assertArrayNotHasKey('cdnfly_api_secret', $listResponse->json('data.data.0'));

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'New Name',
                'email' => 'new@example.test',
                'role' => 'user',
                'cdnfly_user_id' => 2002,
                'email_verified' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.test')
            ->assertJsonPath('data.cdnfly_user_id', 2002)
            ->assertJsonPath('data.email_verified', false);

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.test', $user->email);
        $this->assertSame(2002, $user->cdnfly_user_id);
        $this->assertNull($user->email_verified_at);
    }

    public function test_admin_can_filter_orders_and_services_by_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => '专业版',
            'slug' => 'pro',
            'price_monthly' => 50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $targetOrder = Order::query()->create([
            'order_no' => '202604290001',
            'user_id' => $target->id,
            'product_id' => $product->id,
            'amount_usdt' => 50,
            'pay_address' => 'T1111111111111111111111111111111111',
            'status' => 'paid',
            'gateway_status' => 'paid',
            'expire_at' => now()->addMinutes(10),
            'paid_at' => now(),
        ]);

        Order::query()->create([
            'order_no' => '202604290002',
            'user_id' => $other->id,
            'product_id' => $product->id,
            'amount_usdt' => 50,
            'pay_address' => 'T2222222222222222222222222222222222',
            'status' => 'paid',
            'expire_at' => now()->addMinutes(10),
        ]);

        ServiceInstance::query()->create([
            'user_id' => $target->id,
            'product_id' => $product->id,
            'source_order_id' => $targetOrder->id,
            'status' => 'active',
            'service_name' => 'cdn-202604290001',
            'cdnfly_user_id' => '9001',
            'cdnfly_service_id' => 'svc-9001',
            'opened_at' => now(),
            'expired_at' => now()->addMonth(),
        ]);

        ServiceInstance::query()->create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'status' => 'failed',
            'service_name' => 'other-service',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/orders?user_id={$target->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.user_id', $target->id)
            ->assertJsonPath('data.data.0.product_name', '专业版')
            ->assertJsonPath('data.data.0.order_no', '202604290001');

        $this->actingAs($admin)
            ->getJson("/api/admin/services?user_id={$target->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.user_id', $target->id)
            ->assertJsonPath('data.data.0.product_name', '专业版')
            ->assertJsonPath('data.data.0.order_no', '202604290001')
            ->assertJsonPath('data.data.0.cdnfly_service_id', 'svc-9001');
    }

    public function test_admin_can_manage_nodes_through_cdnfly_gateway(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'admin-key',
            'services.cdnfly.admin_api_secret' => 'admin-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://cdnfly.example.test/v1/master/upgrades')) {
                return Http::response([
                    'code' => 0,
                    'data' => [
                        'version_name' => 'v5.8.12',
                        'ip' => '5.102.124.152',
                        'es_ip' => '5.102.124.152',
                        'es_pwd' => 'test-es-password',
                        'master_host' => 'cdn666.com',
                        'master_port' => '3803',
                        'cc_img_url' => '',
                    ],
                ]);
            }

            if ($request->method() === 'GET' && str_starts_with($url, 'https://cdnfly.example.test/v1/pending-nodes')) {
                return Http::response([
                    'code' => 0,
                    'count' => 1,
                    'data' => [
                        [
                            'id' => 88,
                            'ip' => '198.51.100.10',
                            'create_at' => '2026-04-30 16:20:00',
                        ],
                    ],
                ]);
            }

            return Http::response([
                'code' => 0,
                'data' => [
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'payload' => $request->data(),
                ],
            ]);
        });

        $admin = User::factory()->create(['role' => 'admin']);

        $installResponse = $this->actingAs($admin)
            ->getJson('/api/admin/node-install-command')
            ->assertOk()
            ->assertJsonPath('data.version_name', 'v5.8.12')
            ->assertJsonPath('data.master_ip', '5.102.124.152')
            ->assertJsonPath('data.has_es_pwd', true);

        $this->assertStringContainsString('--master-ver v5.8.12', $installResponse->json('data.command'));
        $this->assertStringContainsString('--master-port 3803', $installResponse->json('data.command'));

        $this->actingAs($admin)
            ->getJson('/api/admin/pending-nodes?page=1&limit=20')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.data.0.id', 88)
            ->assertJsonPath('data.data.0.ip', '198.51.100.10');

        $this->actingAs($admin)
            ->postJson('/api/admin/nodes', [
                'name' => 'Edge Node A',
                'pending_node_id' => 88,
                'region_id' => 3,
                'des' => 'Primary edge node',
                'type' => 'L1',
            ])
            ->assertOk()
            ->assertJsonPath('data.data.method', 'POST')
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/nodes')
            ->assertJsonPath('data.data.payload.name', 'Edge Node A')
            ->assertJsonPath('data.data.payload.pending_node_id', 88)
            ->assertJsonPath('data.data.payload.type', 'L1');

        $this->actingAs($admin)
            ->putJson('/api/admin/nodes/101', [
                'name' => 'Edge Node B',
                'ip' => '10.0.0.11',
                'status' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.data.method', 'PUT')
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/nodes/101')
            ->assertJsonPath('data.data.payload.status', 0);

        $this->actingAs($admin)
            ->putJson('/api/admin/nodes/101/enable', [
                'enable' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.data.method', 'PUT')
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/nodes')
            ->assertJsonPath('data.data.payload.0.id', 101)
            ->assertJsonPath('data.data.payload.0.enable', 0)
            ->assertJsonPath('data.data.payload.0.target', 'node')
            ->assertJsonPath('data.data.payload.0.disable_by', 'admin');

        $this->actingAs($admin)
            ->deleteJson('/api/admin/pending-nodes/88')
            ->assertOk()
            ->assertJsonPath('data.data.method', 'DELETE')
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/pending-nodes/88');

        $this->actingAs($admin)
            ->deleteJson('/api/admin/nodes/101')
            ->assertOk()
            ->assertJsonPath('data.data.method', 'DELETE')
            ->assertJsonPath('data.data.url', 'https://cdnfly.example.test/v1/nodes/101');

        Http::assertSentCount(7);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://cdnfly.example.test/v1/master/upgrades');
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://cdnfly.example.test/v1/pending-nodes'));
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://cdnfly.example.test/v1/nodes'
            && ($request->data()['name'] ?? null) === 'Edge Node A'
            && ($request->data()['pending_node_id'] ?? null) === 88
            && ! array_key_exists('ip', $request->data()));
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://cdnfly.example.test/v1/nodes/101'
            && ($request->data()['status'] ?? null) === 0);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://cdnfly.example.test/v1/nodes'
            && is_array($request->data())
            && ($request->data()[0]['id'] ?? null) === 101
            && ($request->data()[0]['enable'] ?? null) === 0
            && ($request->data()[0]['target'] ?? null) === 'node'
            && ($request->data()[0]['disable_by'] ?? null) === 'admin');
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'https://cdnfly.example.test/v1/pending-nodes/88');
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'https://cdnfly.example.test/v1/nodes/101');
    }

    public function test_admin_can_visit_admin_overview_and_user_management_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/console/admin')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/console/admin/users')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/console/admin/monitoring')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/console/admin/settings')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/console/admin/security')
            ->assertOk();
    }

    public function test_admin_cannot_demote_the_only_admin_or_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'user',
                'cdnfly_user_id' => null,
                'email_verified' => true,
            ])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_access_admin_user_management(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/admin/overview')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_disabled_cdnfly_outbound_returns_local_empty_payloads_without_http_requests(): void
    {
        config([
            'services.cdnfly.outbound_enabled' => false,
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
        ]);

        Http::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.cdnfly.users_total', 0)
            ->assertJsonPath('data.cdnfly.packages_total', 0)
            ->assertJsonPath('data.cdnfly.user_packages_total', 0)
            ->assertJsonPath('data.cdnfly.nodes_total', 0)
            ->assertJsonPath('data.cdnfly.pending_nodes_total', 0)
            ->assertJsonPath('data.cdnfly.sites_total', 0)
            ->assertJsonPath('data.cdnfly.streams_total', 0)
            ->assertJsonPath('data.cdnfly.certs_total', 0)
            ->assertJsonPath('data.cdnfly.acls_total', 0)
            ->assertJsonPath('data.cdnfly.dns_apis_total', 0)
            ->assertJsonPath('data.cdnfly.regions_total', 0)
            ->assertJsonPath('data.cdnfly.node_groups_total', 0)
            ->assertJsonPath('data.cdnfly.package_groups_total', 0)
            ->assertJsonPath('data.cdnfly.cname_domains_total', 0)
            ->assertJsonPath('data.cdnfly.stream_groups_total', 0)
            ->assertJsonPath('data.cdnfly.lines_total', 0);

        $this->actingAs($admin)
            ->getJson('/api/admin/nodes')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.cdnfly_outbound_disabled', true);

        $this->actingAs($admin)
            ->getJson('/api/admin/pending-nodes')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.cdnfly_outbound_disabled', true);

        $this->actingAs($admin)
            ->getJson('/api/admin/node-install-command')
            ->assertOk()
            ->assertJsonPath('data.command', null)
            ->assertJsonPath('data.cdnfly_outbound_disabled', true);

        $this->actingAs($admin)
            ->postJson('/api/admin/nodes', [
                'name' => 'Blocked Node',
                'pending_node_id' => 88,
                'region_id' => 3,
                'des' => '',
                'type' => 'L1',
            ])
            ->assertStatus(502);

        $this->actingAs($admin)
            ->postJson('/api/admin/proxy/v1/packages', ['name' => 'blocked'])
            ->assertStatus(502);

        Http::assertNothingSent();
    }
}
