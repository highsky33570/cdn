<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOrderFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, string $number, string $time, string $type = 'recharge', string $status = 'paid'): Order
    {
        $order = new Order([
            'user_id' => $user->id,
            'order_no' => $number,
            'order_type' => $type,
            'status' => $status,
            'amount_usdt' => 25,
            'actual_paid_amount' => 24.5,
            'pay_currency' => 'USDT',
            'pay_address' => 'local-test-address',
            'expire_at' => '2026-09-26 00:00:00',
        ]);
        $order->created_at = $time;
        $order->save();

        return $order;
    }

    public function test_filters_include_the_whole_end_date_and_only_the_current_users_orders(): void
    {
        $user = User::factory()->create();
        $this->order($user, 'before', '2026-09-23 23:59:59');
        $first = $this->order($user, 'first', '2026-09-24 00:00:00');
        $last = $this->order($user, 'last', '2026-09-24 23:59:59');
        $this->order($user, 'after', '2026-09-25 00:00:00');
        $this->order($user, 'wrong-type', '2026-09-24 12:00:00', 'renew');
        $this->order($user, 'unpaid', '2026-09-24 12:00:00', 'recharge', 'pending');
        $this->order(User::factory()->create(), 'other-user', '2026-09-24 12:00:00');
        $query = '/api/orders?order_type=recharge&status=paid&start=2026-09-24&end=2026-09-24&limit=1';
        $this->actingAs($user)->getJson($query)->assertOk()
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.items.0.id', $last->id)
            ->assertJsonCount(1, 'data.items');
        $this->getJson($query.'&page=2')->assertOk()
            ->assertJsonPath('data.items.0.id', $first->id)->assertJsonPath('data.page', 2);
    }

    public function test_invalid_ranges_and_unknown_types_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            'start=2026-09-25&end=2026-09-24',
            'start=2026-09-24',
            'end=2026-09-24',
            'start=invalid&end=2026-09-24',
            'order_type=unknown',
        ] as $query) {
            $this->getJson('/api/orders?'.$query)->assertUnprocessable();
        }
    }
}
