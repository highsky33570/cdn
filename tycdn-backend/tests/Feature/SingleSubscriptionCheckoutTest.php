<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Customers may hold one live package at a time.
 *
 * Renewing that package is fine; stacking a second tier beside it is not —
 * that used to create parallel CDNfly user-packages for the same account.
 */
class SingleSubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_purchase_is_blocked_when_an_active_service_exists(): void
    {
        $this->fakeEpusdt();

        $user = User::factory()->create();
        $product = $this->product('advanced');
        $other = $this->product('professional');

        ServiceInstance::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'active',
            'service_name' => 'existing',
            'opened_at' => now()->subDay(),
            'expired_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/payments/epusdt/create', [
                'product_id' => $other->id,
                'order_type' => 'new',
                'billing_cycle' => 'monthly',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_new_purchase_is_allowed_without_an_active_service(): void
    {
        $this->fakeEpusdt();

        $user = User::factory()->create();
        $product = $this->product('advanced-free');

        $this->actingAs($user)
            ->postJson('/api/payments/epusdt/create', [
                'product_id' => $product->id,
                'order_type' => 'new',
                'billing_cycle' => 'monthly',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'order_type' => 'new',
        ]);
    }

    public function test_quantity_greater_than_one_is_rejected(): void
    {
        $this->fakeEpusdt();

        $user = User::factory()->create();
        $product = $this->product('advanced-qty');

        $this->actingAs($user)
            ->postJson('/api/payments/epusdt/create', [
                'product_id' => $product->id,
                'order_type' => 'new',
                'billing_cycle' => 'monthly',
                'quantity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_renew_is_allowed_with_an_active_service(): void
    {
        $this->fakeEpusdt();

        $user = User::factory()->create();
        $product = $this->product('advanced-renew');

        $service = ServiceInstance::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'active',
            'service_name' => 'renew-me',
            'opened_at' => now()->subDay(),
            'expired_at' => now()->addDays(3),
        ]);

        $this->actingAs($user)
            ->postJson('/api/payments/epusdt/create', [
                'product_id' => $product->id,
                'order_type' => 'renew',
                'billing_cycle' => 'monthly',
                'service_instance_id' => $service->id,
            ])
            ->assertCreated();
    }

    private function product(string $slug): Product
    {
        return Product::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => 50,
            'price_quarterly' => 150,
            'price_yearly' => 600,
            'currency' => 'USD',
            'is_active' => true,
        ]);
    }

    private function fakeEpusdt(): void
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
                    'trade_id' => 'TRADE-SINGLE',
                    'amount' => 50,
                    'currency' => 'usd',
                    'actual_amount' => 50,
                    'receive_address' => 'TEpusdtPayAddress',
                    'token' => 'usdt',
                    'payment_url' => 'https://pay.example.test/checkout/TRADE-SINGLE',
                    'expiration_time' => now()->addMinutes(30)->timestamp,
                ],
            ]),
        ]);
    }
}
