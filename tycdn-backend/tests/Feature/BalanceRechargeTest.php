<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CdnflyApiService;
use App\Services\CdnflyProvisionService;
use App\Support\OrderStatus;
use App\Support\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Balance top-up: the second way to pay.
 *
 * A recharge buys credit rather than a package, so it carries no product and no
 * billing cycle, and settles by calling CDNfly's recharge endpoint instead of
 * provisioning. It reuses the order/payment machinery, which means it also
 * inherits the callback signature check, idempotency, the provisioning lock and
 * the reconcile retry cap.
 */
class BalanceRechargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.epusdt.base_url' => 'https://pay.example.test',
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
            'services.epusdt.notify_url' => 'https://console.example.test/api/payments/epusdt/notify',
            'services.epusdt.recharge_min' => 1,
            'services.epusdt.recharge_max' => 10000,
            'services.cdnfly.base_url' => 'https://panel.example.test',
            'services.cdnfly.outbound_enabled' => true,
        ]);
    }

    public function test_a_recharge_order_carries_no_product_and_the_chosen_amount(): void
    {
        $this->fakeGateway();

        $user = $this->cdnflyUser();

        $this->actingAs($user)
            ->postJson('/api/orders/recharge', ['amount' => 25])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $order = Order::firstOrFail();

        $this->assertSame(OrderType::RECHARGE, $order->order_type);
        $this->assertNull($order->product_id, 'a recharge is not tied to a product');
        $this->assertNull($order->billing_cycle);
        // numeric, not string: decimal formatting differs between sqlite and mysql
        $this->assertEqualsWithDelta(25.0, (float) $order->fiat_amount, 0.001);
        $this->assertSame(OrderStatus::PENDING, $order->status);
    }

    public function test_amount_outside_the_configured_bounds_is_rejected(): void
    {
        Http::fake();
        $user = $this->cdnflyUser();

        $this->actingAs($user)
            ->postJson('/api/orders/recharge', ['amount' => 0.5])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/orders/recharge', ['amount' => 99999])
            ->assertStatus(422);

        $this->assertSame(0, Order::count(), 'no order should be opened for an invalid amount');
        Http::assertNothingSent();
    }

    /**
     * Without an upstream account there is nowhere for the credit to land, so
     * refuse before taking money rather than after.
     */
    public function test_a_user_with_no_cdnfly_account_cannot_recharge(): void
    {
        Http::fake();

        $user = User::factory()->create(['cdnfly_user_id' => null]);

        $this->actingAs($user)
            ->postJson('/api/orders/recharge', ['amount' => 25])
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_a_paid_recharge_credits_the_cdnfly_balance(): void
    {
        $user = $this->cdnflyUser();
        $order = $this->paidRecharge($user, quoted: 50, paid: 50);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('rechargeUser')->once()->with(4321, 50.0)->andReturn(['code' => 0]);
        $cdnfly->shouldNotReceive('purchaseUserPackage');

        $result = app(CdnflyProvisionService::class)->provisionPaidOrder($order);

        $this->assertSame('success', $result['status']);
        $this->assertSame(OrderType::RECHARGE, $result['provision_action']);

        $order->refresh();
        $this->assertSame(OrderStatus::ACTIVE, $order->status);
        $this->assertNotNull($order->provisioned_at, 'provisioned_at is what stops the reconcile sweep retrying');
    }

    /**
     * The credit must follow the money. Crediting the quoted figure on an
     * underpaid order would hand out balance nobody paid for.
     */
    public function test_an_underpaid_recharge_credits_only_what_was_actually_paid(): void
    {
        $user = $this->cdnflyUser();
        $order = $this->paidRecharge($user, quoted: 50, paid: 20);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('rechargeUser')->once()->with(4321, 20.0)->andReturn(['code' => 0]);

        $this->assertSame('success', app(CdnflyProvisionService::class)->provisionPaidOrder($order)['status']);
    }

    public function test_a_failed_upstream_recharge_does_not_mark_the_order_complete(): void
    {
        $user = $this->cdnflyUser();
        $order = $this->paidRecharge($user, quoted: 30, paid: 30);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('rechargeUser')->once()->andThrow(new \RuntimeException('CDNfly recharge user failed: 余额服务异常'));

        $result = app(CdnflyProvisionService::class)->provisionPaidOrder($order);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('recharge_failed', $result['failure_code']);

        $order->refresh();
        $this->assertNull($order->provisioned_at, 'an unsettled recharge must stay eligible for retry');
        $this->assertSame(OrderStatus::PAID, $order->status);
    }

    private function cdnflyUser(): User
    {
        return User::factory()->create([
            'cdnfly_user_id' => 4321,
            'cdnfly_api_key' => 'k',
            'cdnfly_api_secret' => 's',
        ]);
    }

    private function paidRecharge(User $user, float $quoted, float $paid): Order
    {
        return Order::create([
            'order_no' => 'TYRECHARGE'.$quoted.$paid,
            'user_id' => $user->id,
            'product_id' => null,
            'order_type' => OrderType::RECHARGE,
            'fiat_amount' => $quoted,
            'fiat_currency' => 'USD',
            'actual_paid_amount' => $paid,
            'amount_usdt' => $paid,
            'pay_address' => 'TAddr',
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
        ]);
    }

    private function fakeGateway(): void
    {
        Http::fake([
            'https://pay.example.test/*' => Http::response([
                'status_code' => 200,
                'data' => [
                    'trade_id' => 'TRADE-RECHARGE',
                    'amount' => 25,
                    'actual_amount' => 25,
                    'token' => 'usdt',
                    'receive_address' => 'TRechargeAddress',
                    'payment_url' => 'https://pay.example.test/checkout/1',
                    'expiration_time' => now()->addMinutes(30)->timestamp,
                ],
            ]),
        ]);
    }
}
