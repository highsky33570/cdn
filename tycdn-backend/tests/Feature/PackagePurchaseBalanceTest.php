<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use App\Models\User;
use App\Services\CdnflyApiService;
use App\Services\CdnflyProvisionService;
use App\Support\OrderStatus;
use App\Support\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A USDT-paid package purchase, settled against CDNfly's prepaid balance.
 *
 * CDNfly deducts the customer's balance at the package's real price when a
 * package is bought. The customer paid us in USDT, not into that balance, so
 * provisioning first tops the balance up by what they paid, then buys — the
 * balance is the intermediary CDNfly requires, and nets to zero. The top-up
 * must happen exactly once even though provisioning is retried.
 */
class PackagePurchaseBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cdnfly.outbound_enabled' => true]);
    }

    public function test_a_paid_purchase_tops_up_the_balance_then_buys(): void
    {
        $order = $this->paidNewOrder(amount: 5);

        $rechargeArgs = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('rechargeUser')
            ->once()
            ->andReturnUsing(function (int $uid, float $amount) use (&$rechargeArgs) {
                $rechargeArgs = [$uid, $amount];

                return ['code' => 0];
            });
        $cdnfly->shouldReceive('purchaseUserPackage')
            ->once()
            ->andReturn(['code' => 0, 'data' => ['id' => 900]]);

        app(CdnflyProvisionService::class)->provisionPaidOrder($order);

        $this->assertSame([7, 5.0], $rechargeArgs);
        $this->assertNotNull($order->fresh()->balance_credited_at);
    }

    /**
     * The credit lands in CDNfly before the purchase; if the purchase then
     * fails, the retry must buy again WITHOUT crediting a second time.
     */
    public function test_a_retry_after_a_failed_purchase_does_not_recredit(): void
    {
        $order = $this->paidNewOrder(amount: 5);

        $cdnfly = $this->mock(CdnflyApiService::class);

        // First attempt: credit succeeds, purchase throws.
        $cdnfly->shouldReceive('rechargeUser')->once()->andReturn(['code' => 0]);
        $cdnfly->shouldReceive('purchaseUserPackage')
            ->once()
            ->andThrow(new \RuntimeException('CDNfly purchase user package failed: 系统错误'));

        app(CdnflyProvisionService::class)->provisionPaidOrder($order);

        $this->assertNotNull($order->fresh()->balance_credited_at);

        // Second attempt: NO further recharge, purchase retried and succeeds.
        $cdnfly2 = $this->mock(CdnflyApiService::class);
        $cdnfly2->shouldNotReceive('rechargeUser');
        $cdnfly2->shouldReceive('purchaseUserPackage')
            ->once()
            ->andReturn(['code' => 0, 'data' => ['id' => 900]]);

        app(CdnflyProvisionService::class)->provisionPaidOrder($order->fresh());
    }

    /** A failed top-up must abort before any purchase is attempted. */
    public function test_a_failed_top_up_aborts_before_purchase(): void
    {
        $order = $this->paidNewOrder(amount: 5);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('rechargeUser')
            ->once()
            ->andThrow(new \RuntimeException('CDNfly recharge user failed: 系统错误'));
        $cdnfly->shouldNotReceive('purchaseUserPackage');

        app(CdnflyProvisionService::class)->provisionPaidOrder($order);

        $this->assertNull($order->fresh()->balance_credited_at);
        $this->assertNotSame(OrderStatus::ACTIVE, $order->fresh()->status);
    }

    /** A zero-priced package needs no balance and still provisions. */
    public function test_a_zero_priced_purchase_skips_the_top_up(): void
    {
        $order = $this->paidNewOrder(amount: 0);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('rechargeUser');
        $cdnfly->shouldReceive('purchaseUserPackage')
            ->once()
            ->andReturn(['code' => 0, 'data' => ['id' => 900]]);

        app(CdnflyProvisionService::class)->provisionPaidOrder($order);

        $this->assertNotNull($order->fresh()->balance_credited_at);
    }

    private function paidNewOrder(float $amount): Order
    {
        $user = User::factory()->create([
            'cdnfly_user_id' => 7,
            'cdnfly_api_key' => 'key-7',
            'cdnfly_api_secret' => 'secret-7',
        ]);

        $product = Product::query()->create([
            'name' => 'JPN-Mini', 'slug' => 'jpn-mini-'.uniqid(),
            'price_monthly' => $amount, 'price_quarterly' => $amount,
            'price_yearly' => $amount, 'currency' => 'USD',
        ]);

        ProductCdnflyMapping::query()->create([
            'product_id' => $product->id,
            'cdnfly_plan_id' => '1',
            'provision_payload' => ['package' => 1, 'duration' => 'month'],
        ]);

        return Order::query()->create([
            'order_no' => 'ORD'.uniqid(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_type' => OrderType::NEW,
            'status' => OrderStatus::PAID,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'amount_usdt' => $amount,
            'fiat_amount' => $amount,
            'actual_paid_amount' => $amount,
            'pay_address' => 'T'.uniqid(),
            'expire_at' => now()->addHour(),
            'paid_at' => now(),
        ]);
    }
}
