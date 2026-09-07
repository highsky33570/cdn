<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerifiedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_create_order(): void
    {
        $user = User::factory()->unverified()->create();
        $product = Product::create([
            'name' => 'Starter',
            'slug' => 'starter-verified-test',
            'price_monthly' => 9.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/orders', [
                'product_id' => $product->id,
                'billing_cycle' => 'monthly',
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_unverified_user_cannot_call_cdn_sites(): void
    {
        Http::fake();

        $user = User::factory()->unverified()->create([
            'cdnfly_api_key' => 'key',
            'cdnfly_api_secret' => 'secret',
        ]);

        $this->actingAs($user)
            ->getJson('/api/cdn/sites')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_unverified_admin_cannot_access_admin_overview(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/overview')
            ->assertForbidden();
    }

    public function test_unverified_user_can_still_call_me(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_unverified_user_can_resend_verification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/resend-verification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_passes_admin_overview_middleware(): void
    {
        config(['services.cdnfly.outbound_enabled' => false]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/overview')
            ->assertOk();
    }

    public function test_admin_store_user_sends_verification_email_and_creates_unverified_account(): void
    {
        Notification::fake();
        config(['services.cdnfly.outbound_enabled' => false]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Newcomer',
                'email' => 'newcomer@example.test',
                'password' => 'CorrectHorseBatteryStaple1',
                'role' => 'user',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'newcomer@example.test')
            ->assertJsonPath('data.email_verified', false);

        $newUser = User::where('email', 'newcomer@example.test')->firstOrFail();

        $this->assertNull($newUser->email_verified_at);
        Notification::assertSentTo($newUser, VerifyEmail::class);

        $this->assertSame('Newcomer', $response->json('data.name'));
    }
}
