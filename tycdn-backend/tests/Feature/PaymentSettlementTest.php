<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Services\CdnflyProvisionService;
use App\Services\EpusdtService;
use App\Services\PaymentReconciliationService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.epusdt.pid' => '1000',
            'services.epusdt.api_token' => 'epusdt-secret',
        ]);
    }

    /**
     * The regression this guards: an on-chain transfer that confirms after the
     * order window used to hit the replay guard, log "replay ignored" and return
     * ok -- keeping the customer's money and never delivering the service.
     */
    public function test_late_payment_on_an_expired_order_is_settled_not_ignored(): void
    {
        $order = $this->makeOrder(OrderStatus::EXPIRED, 'TRADE-LATE', 'TYLATEORDER', [
            'expire_at' => now()->subHours(2),
            'cancelled_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/payments/epusdt/notify', $this->signedPayload([
            'trade_id' => 'TRADE-LATE',
            'order_id' => 'TYLATEORDER',
            'block_transaction_id' => 'late-tx-1',
        ]))->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::PAID, $order->status, 'a real late payment must settle the order');
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->cancelled_at, 'reviving an expired order must clear the cancellation stamp');
        $this->assertSame(1, PaymentTransaction::where('txid', 'late-tx-1')->count());
    }

    public function test_late_payment_on_a_cancelled_order_is_also_settled(): void
    {
        $this->makeOrder(OrderStatus::CANCELLED, 'TRADE-CANCEL', 'TYCANCELORDER', [
            'expire_at' => now()->subHours(1),
        ]);

        $this->postJson('/api/payments/epusdt/notify', $this->signedPayload([
            'trade_id' => 'TRADE-CANCEL',
            'order_id' => 'TYCANCELORDER',
            'block_transaction_id' => 'cancel-tx-1',
        ]))->assertOk();

        $this->assertSame(OrderStatus::PAID, Order::where('order_no', 'TYCANCELORDER')->first()->status);
    }

    /**
     * Widening the settle window must not weaken replay protection: an order that
     * has already been settled stays untouched and no second transaction is written.
     */
    public function test_already_settled_order_still_ignores_replayed_callbacks(): void
    {
        $order = $this->makeOrder(OrderStatus::ACTIVE, 'TRADE-DONE', 'TYDONEORDER', [
            'paid_at' => now()->subHour(),
            'actual_paid_amount' => 9.99,
        ]);

        $this->postJson('/api/payments/epusdt/notify', $this->signedPayload([
            'trade_id' => 'TRADE-DONE',
            'order_id' => 'TYDONEORDER',
            'block_transaction_id' => 'done-tx-1',
        ]))->assertOk();

        $this->assertSame(OrderStatus::ACTIVE, $order->fresh()->status);
        $this->assertSame(0, PaymentTransaction::where('txid', 'done-tx-1')->count());
    }

    /**
     * The notify callback, the reconcile cron and POST /orders/{no}/provision can
     * all reach provisioning at once; a double run buys the upstream package twice.
     */
    public function test_provisioning_refuses_to_run_while_another_worker_holds_the_lock(): void
    {
        $order = $this->makeOrder(OrderStatus::PAID, 'TRADE-LOCK', 'TYLOCKORDER', [
            'paid_at' => now(),
        ]);

        $lock = Cache::lock('cdnfly-provision:'.$order->id, 300);
        $this->assertTrue($lock->get(), 'precondition: the competing worker takes the lock');

        try {
            $result = app(CdnflyProvisionService::class)->provisionPaidOrder($order);
        } finally {
            $lock->release();
        }

        $this->assertSame('already_processing_or_active', $result['status']);
    }

    public function test_reconciliation_stops_retrying_an_order_that_exhausted_its_attempts(): void
    {
        config(['services.cdnfly.max_provision_attempts' => 3]);

        $order = $this->makeOrder(OrderStatus::PAID, 'TRADE-GIVEUP', 'TYGIVEUPORDER', [
            'paid_at' => now(),
        ]);

        ServiceInstance::create([
            'user_id' => $order->user_id,
            'product_id' => $order->product_id,
            'source_order_id' => $order->id,
            'status' => 'failed',
            'extra' => ['provision_attempts' => 3],
        ]);

        $provision = $this->mock(CdnflyProvisionService::class);
        $provision->shouldNotReceive('provisionPaidOrder');

        $service = new PaymentReconciliationService(app(EpusdtService::class), $provision);

        $this->assertSame(0, $service->reconcile()['provisioning_attempts']);
    }

    public function test_reconciliation_still_retries_an_order_below_the_cap(): void
    {
        config(['services.cdnfly.max_provision_attempts' => 3]);

        $order = $this->makeOrder(OrderStatus::PAID, 'TRADE-RETRY', 'TYRETRYORDER', [
            'paid_at' => now(),
        ]);

        ServiceInstance::create([
            'user_id' => $order->user_id,
            'product_id' => $order->product_id,
            'source_order_id' => $order->id,
            'status' => 'failed',
            'extra' => ['provision_attempts' => 1],
        ]);

        $provision = $this->mock(CdnflyProvisionService::class);
        $provision->shouldReceive('provisionPaidOrder')->once()->andReturn(['status' => 'queued']);

        $service = new PaymentReconciliationService(app(EpusdtService::class), $provision);

        $this->assertSame(1, $service->reconcile()['provisioning_attempts']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(string $status, string $tradeId, string $orderNo, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_no' => $orderNo,
            'user_id' => User::factory()->create()->id,
            'product_id' => Product::create([
                'name' => 'Starter',
                'slug' => 'starter-'.strtolower($orderNo),
                'price_monthly' => 10,
                'currency' => 'USD',
                'is_active' => true,
            ])->id,
            'amount_usdt' => 9.99,
            'pay_address' => 'TEpusdtPayAddress',
            'status' => $status,
            'expire_at' => now()->addMinutes(30),
            'gateway_provider' => 'epusdt',
            'gateway_trade_id' => $tradeId,
            'gateway_amount' => 10,
            'gateway_actual_amount' => 9.99,
            'gateway_status' => 'pending',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signedPayload(array $overrides): array
    {
        $payload = array_merge([
            'pid' => '1000',
            'amount' => 10,
            'actual_amount' => 9.99,
            'receive_address' => 'TEpusdtPayAddress',
            'token' => 'usdt',
            'status' => 2,
        ], $overrides);

        $payload['signature'] = $this->epusdtSignature($payload, 'epusdt-secret');

        return $payload;
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
