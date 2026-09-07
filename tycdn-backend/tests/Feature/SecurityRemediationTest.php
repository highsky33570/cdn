<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\CdnflyApiService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_routes_require_authentication(): void
    {
        $this->postJson('/api/orders', [
            'amount_usdt' => 10,
            'pay_address' => 'TUnauthenticatedPayAddress',
        ])->assertUnauthorized();
    }

    public function test_user_cannot_read_or_provision_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::create([
            'order_no' => 'TYOTHERORDER',
            'user_id' => $otherUser->id,
            'amount_usdt' => 10,
            'pay_address' => 'TOtherUserPayAddress',
            'status' => OrderStatus::PAID,
            'expire_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($user)->getJson("/api/orders/{$order->order_no}")
            ->assertNotFound();

        $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/provision")
            ->assertNotFound();
    }

    public function test_epusdt_create_uses_authenticated_user_and_server_side_price(): void
    {
        config([
            'services.epusdt.base_url' => 'https://pay.example.test',
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
            'services.epusdt.notify_url' => 'https://api.example.test/api/payments/epusdt/notify',
            'services.epusdt.redirect_url' => 'https://portal.example.test/payment/result',
        ]);

        Http::fake([
            'https://pay.example.test/*' => Http::response([
                'status_code' => 200,
                'data' => [
                    'trade_id' => 'TRADE-1',
                    'amount' => 19.98,
                    'currency' => 'usd',
                    'actual_amount' => 19.98,
                    'receive_address' => 'TEpusdtPayAddress',
                    'token' => 'usdt',
                    'payment_url' => 'https://pay.example.test/checkout/TRADE-1',
                    'expiration_time' => now()->addMinutes(30)->timestamp,
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 9.99,
            'price_quarterly' => 25.99,
            'price_yearly' => 99.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/payments/epusdt/create', [
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 2,
            'user_id' => $otherUser->id,
            'fiat_amount' => 0.01,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', 19.98);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'fiat_amount' => 19.98,
            'gateway_amount' => 19.98,
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://pay.example.test/payments/gmpay/v1/order/create-transaction'
                && ($payload['pid'] ?? null) === '1000'
                && ! array_key_exists('user_id', $payload)
                && ! array_key_exists('fiat_amount', $payload)
                && ($payload['signature'] ?? '') === $this->epusdtSignature($payload, 'epusdt-secret');
        });
    }

    public function test_epusdt_paid_callback_is_idempotent_for_replays(): void
    {
        config([
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
        ]);

        $order = Order::create([
            'order_no' => 'TYEPUSDTORDER',
            'user_id' => User::factory()->create()->id,
            'product_id' => Product::create([
                'name' => 'Starter',
                'slug' => 'starter-callback',
                'price_monthly' => 10,
                'currency' => 'USD',
                'is_active' => true,
            ])->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddress',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_trade_id' => 'TRADE-REPLAY',
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_status' => 'pending',
        ]);

        $payload = [
            'pid' => '1000',
            'trade_id' => 'TRADE-REPLAY',
            'order_id' => 'TYEPUSDTORDER',
            'amount' => 10,
            'actual_amount' => 9.99,
            'receive_address' => 'TEpusdtPayAddress',
            'token' => 'usdt',
            'block_transaction_id' => 'epusdt-tx-1',
            'status' => 2,
        ];
        $payload['signature'] = $this->epusdtSignature($payload, 'epusdt-secret');

        $this->postJson('/api/payments/epusdt/notify', $payload)->assertOk();
        $this->postJson('/api/payments/epusdt/notify', $payload)->assertOk();

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(1, PaymentTransaction::where('txid', 'epusdt-tx-1')->count());
    }

    public function test_epusdt_paid_callback_rejects_actual_amount_mismatch(): void
    {
        config([
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
        ]);

        $order = Order::create([
            'order_no' => 'TYEPUSDTAMOUNTMISMATCH',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddress',
            'pay_currency' => 'USDT',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_trade_id' => 'TRADE-AMOUNT-MISMATCH',
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_token' => 'usdt',
            'gateway_status' => 'pending',
        ]);

        $payload = [
            'pid' => '1000',
            'trade_id' => 'TRADE-AMOUNT-MISMATCH',
            'order_id' => 'TYEPUSDTAMOUNTMISMATCH',
            'amount' => 10,
            'actual_amount' => 9.98,
            'receive_address' => 'TEpusdtPayAddress',
            'token' => 'usdt',
            'block_transaction_id' => 'epusdt-amount-mismatch-tx',
            'status' => 2,
        ];
        $payload['signature'] = $this->epusdtSignature($payload, 'epusdt-secret');

        $this->postJson('/api/payments/epusdt/notify', $payload)->assertStatus(422);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(0, PaymentTransaction::where('txid', 'epusdt-amount-mismatch-tx')->count());
    }

    public function test_user_proxy_blocks_unlisted_paths_and_privileged_fields(): void
    {
        $user = User::factory()->create([
            'cdnfly_api_key' => 'key',
            'cdnfly_api_secret' => 'secret',
        ]);

        $this->actingAs($user)->getJson('/api/cdn/proxy/v1/users')
            ->assertForbidden();

        $this->actingAs($user)->postJson('/api/cdn/proxy/v1/sites', [
            'name' => 'example.com',
            'user_id' => 123,
        ])->assertStatus(422);
    }

    public function test_user_cdn_resource_routes_reject_privileged_fields_like_proxy(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'cdnfly_api_key' => 'key',
            'cdnfly_api_secret' => 'secret',
        ]);

        $this->actingAs($user)->getJson('/api/cdn/sites?user_id=123')
            ->assertStatus(422);

        $this->actingAs($user)->postJson('/api/cdn/sites', [
            'name' => 'example.com',
            'status' => 'active',
        ])->assertStatus(422);

        $this->actingAs($user)->putJson('/api/cdn/certs/1', [
            'name' => 'cert',
            'meta' => [
                'type' => 'admin-only',
            ],
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_admin_proxy_blocks_sensitive_paths_and_privileged_fields(): void
    {
        app()->instance(CdnflyApiService::class, new CdnflyApiService);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/admin/proxy/v1/users')
            ->assertForbidden();

        $this->actingAs($admin)->postJson('/api/admin/proxy/v1/sites', [
            'name' => 'example.com',
            'status' => 'active',
        ])->assertStatus(422);
    }

    public function test_api_registration_rejects_short_weak_passwords(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'weakpassuser',
            'email' => 'weakpass@example.com',
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ])->assertStatus(422);
    }

    public function test_forgot_password_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();

        $knownUser = User::factory()->create();
        $knownResponse = $this->postJson(route('password.email'), [
            'email' => $knownUser->email,
            'captcha' => 'test-captcha',
        ]);
        $unknownResponse = $this->postJson(route('password.email'), [
            'email' => 'missing@example.com',
            'captcha' => 'test-captcha',
        ]);

        $knownResponse->assertOk();
        $unknownResponse->assertOk();
        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
    }

    public function test_tron_reconciliation_leaves_duplicate_amount_matches_ambiguous(): void
    {
        config([
            'services.tron.receive_address' => 'TServerOwnedReceiveAddress',
            'services.tron.grid_base_url' => 'https://api.trongrid.test',
            'services.tron.reconcile_enabled' => true,
        ]);

        $firstOrder = Order::create([
            'order_no' => 'TYDUPLICATEONE',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 12.34,
            'pay_address' => 'TServerOwnedReceiveAddress',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'tron',
            'gateway_amount' => 12.34,
            'gateway_status' => 'pending',
        ]);
        $secondOrder = Order::create([
            'order_no' => 'TYDUPLICATETWO',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 12.34,
            'pay_address' => 'TServerOwnedReceiveAddress',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'tron',
            'gateway_amount' => 12.34,
            'gateway_status' => 'pending',
        ]);

        Http::fake([
            'https://api.trongrid.test/*' => Http::response([
                'data' => [[
                    'transaction_id' => 'tron-ambiguous-tx',
                    'from' => 'TFromAddress',
                    'to' => 'TServerOwnedReceiveAddress',
                    'value' => '12340000',
                    'block_timestamp' => now()->timestamp * 1000,
                    'token_info' => [
                        'decimals' => 6,
                    ],
                ]],
            ]),
        ]);

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertSame(OrderStatus::PENDING, $firstOrder->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $secondOrder->fresh()->status);
        $this->assertSame(0, PaymentTransaction::where('txid', 'tron-ambiguous-tx')->count());
    }

    public function test_epusdt_reconciliation_rejects_mismatched_order_identity(): void
    {
        config([
            'services.tron.reconcile_enabled' => false,
            'services.epusdt.base_url' => 'https://pay.example.test',
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
            'services.epusdt.query_order_path' => '/query',
        ]);

        $wrongOrderId = Order::create([
            'order_no' => 'TYEPUSDTWRONGORDER',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddressOne',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_trade_id' => 'TRADE-ORDER',
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_status' => 'pending',
        ]);
        $wrongTradeId = Order::create([
            'order_no' => 'TYEPUSDTWRONGTRADE',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddressTwo',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_trade_id' => 'TRADE-EXPECTED',
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_status' => 'pending',
        ]);
        $amountOnly = Order::create([
            'order_no' => 'TYEPUSDTAMOUNTONLY',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddressThree',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_status' => 'pending',
        ]);

        Http::fakeSequence()
            ->push([
                'status_code' => 200,
                'data' => [
                    'status' => 2,
                    'order_id' => 'TYEPUSDTOTHERORDER',
                    'trade_id' => 'TRADE-ORDER',
                    'amount' => 10,
                    'actual_amount' => 9.99,
                    'receive_address' => 'TEpusdtPayAddressOne',
                    'token' => 'usdt',
                    'block_transaction_id' => 'epusdt-wrong-order-tx',
                ],
            ])
            ->push([
                'status_code' => 200,
                'data' => [
                    'status' => 2,
                    'order_id' => 'TYEPUSDTWRONGTRADE',
                    'trade_id' => 'TRADE-WRONG',
                    'amount' => 10,
                    'actual_amount' => 9.99,
                    'receive_address' => 'TEpusdtPayAddressTwo',
                    'token' => 'usdt',
                    'block_transaction_id' => 'epusdt-wrong-trade-tx',
                ],
            ])
            ->push([
                'status_code' => 200,
                'data' => [
                    'status' => 2,
                    'amount' => 10,
                    'actual_amount' => 9.99,
                    'receive_address' => 'TEpusdtPayAddressThree',
                    'token' => 'usdt',
                    'block_transaction_id' => 'epusdt-amount-only-tx',
                ],
            ]);

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertSame(OrderStatus::PENDING, $wrongOrderId->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $wrongTradeId->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $amountOnly->fresh()->status);
        $this->assertSame(0, PaymentTransaction::whereIn('txid', [
            'epusdt-wrong-order-tx',
            'epusdt-wrong-trade-tx',
            'epusdt-amount-only-tx',
        ])->count());
    }

    public function test_epusdt_reconciliation_rejects_actual_amount_mismatch(): void
    {
        config([
            'services.tron.reconcile_enabled' => false,
            'services.epusdt.base_url' => 'https://pay.example.test',
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
            'services.epusdt.query_order_path' => '/query',
        ]);

        $order = Order::create([
            'order_no' => 'TYEPUSDTQUERYAMOUNT',
            'user_id' => User::factory()->create()->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddress',
            'pay_currency' => 'USDT',
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_trade_id' => 'TRADE-QUERY-AMOUNT',
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_token' => 'usdt',
            'gateway_status' => 'pending',
        ]);

        Http::fake([
            'https://pay.example.test/*' => Http::response([
                'status_code' => 200,
                'data' => [
                    'status' => 2,
                    'order_id' => 'TYEPUSDTQUERYAMOUNT',
                    'trade_id' => 'TRADE-QUERY-AMOUNT',
                    'amount' => 10,
                    'actual_amount' => 9.98,
                    'receive_address' => 'TEpusdtPayAddress',
                    'token' => 'usdt',
                    'block_transaction_id' => 'epusdt-query-amount-mismatch-tx',
                ],
            ]),
        ]);

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(0, PaymentTransaction::where('txid', 'epusdt-query-amount-mismatch-tx')->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function epusdtSignature(array $payload, string $secret): string
    {
        unset($payload['signature']);
        ksort($payload, SORT_STRING);

        $parts = [];
        foreach ($payload as $key => $value) {
            if ($value === '' || $value === null || ! is_scalar($value)) {
                continue;
            }

            $parts[] = $key.'='.(is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return strtolower(md5(implode('&', $parts).$secret));
    }
}
