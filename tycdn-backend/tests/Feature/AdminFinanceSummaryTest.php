<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The finance page opened onto three empty tables and answered no question an
 * operator actually arrives with. These are the numbers that replace it.
 *
 * Computed in the database, not by counting the rows on the current page — a
 * KPI derived from page one of a paginated list is wrong as soon as there is a
 * page two.
 */
class AdminFinanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Revenue counts every status from PAID onward. PROVISIONING and ACTIVE are
     * later stages of the same sale; excluding them would under-report every
     * completed order.
     */
    public function test_revenue_counts_all_settled_stages_of_a_sale(): void
    {
        $this->order(OrderStatus::PAID, '5.00');
        $this->order(OrderStatus::PROVISIONING, '10.00');
        $this->order(OrderStatus::ACTIVE, '20.00');

        // Not money.
        $this->order(OrderStatus::PENDING, '99.00');
        $this->order(OrderStatus::FAILED, '99.00');
        $this->order(OrderStatus::CANCELLED, '99.00');

        // JSON renders 35.0 as 35, so compare numerically rather than by type.
        $this->assertEqualsWithDelta(35.0, (float) $this->summary()->json('data.revenue_total'), 0.001);
    }

    public function test_monthly_revenue_excludes_earlier_months(): void
    {
        $this->order(OrderStatus::PAID, '10.00', now());
        $this->order(OrderStatus::PAID, '25.00', now()->subMonths(2));

        $data = $this->summary()->json('data');

        $this->assertEqualsWithDelta(10.0, (float) $data['revenue_month'], 0.001);
        $this->assertEqualsWithDelta(35.0, (float) $data['revenue_total'], 0.001);
    }

    /** Paid but undelivered is the one number worth acting on today. */
    public function test_failed_orders_are_counted_separately(): void
    {
        $this->order(OrderStatus::FAILED, '5.00');
        $this->order(OrderStatus::FAILED, '5.00');
        $this->order(OrderStatus::PENDING, '5.00');

        $this->summary()
            ->assertJsonPath('data.orders_failed', 2)
            ->assertJsonPath('data.orders_pending', 1)
            ->assertJsonPath('data.orders_total', 3);
    }

    public function test_active_services_are_counted(): void
    {
        $user = User::factory()->create();

        ServiceInstance::query()->create(['user_id' => $user->id, 'status' => 'active']);
        ServiceInstance::query()->create(['user_id' => $user->id, 'status' => 'active']);
        ServiceInstance::query()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->summary()
            ->assertJsonPath('data.services_active', 2)
            ->assertJsonPath('data.services_total', 3);
    }

    /** A fresh install must render zeroes, not an error. */
    public function test_an_empty_install_reports_zeroes(): void
    {
        $this->summary()
            ->assertJsonPath('data.revenue_total', 0)
            ->assertJsonPath('data.orders_total', 0)
            ->assertJsonPath('data.services_active', 0);
    }

    public function test_a_non_admin_cannot_read_finance_totals(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->getJson('/api/admin/finance/summary')
            ->assertForbidden();
    }

    private function summary(): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/finance/summary')
            ->assertOk();
    }

    private function order(string $status, string $amount, $paidAt = null): Order
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'P'.uniqid(),
            'slug' => 'p-'.uniqid(),
            'price_monthly' => $amount,
            'price_quarterly' => $amount,
            'price_yearly' => $amount,
            'currency' => 'USD',
        ]);

        return Order::query()->create([
            'order_no' => 'ORD'.uniqid(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => $status,
            'amount_usdt' => $amount,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'paid_at' => $paidAt ?? now(),
            // NOT NULL on the orders table; EPUSDT fills these in real flows.
            'pay_address' => 'T'.uniqid(),
            'expire_at' => now()->addHour(),
        ]);
    }
}
