<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'admin-key',
            'services.cdnfly.admin_api_secret' => 'admin-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);
        Http::fake(fn () => Http::response(['code' => 0, 'data' => [], 'count' => 0]));
    }

    public function test_discount_and_coupon_payloads_preserve_native_fields_and_null_limits(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $discount = ['name' => 'Group discount', 'cate' => 'package', 'user_group' => '1,2', 'package' => '3', 'dis_type' => 'discount', 'discount_value' => '0.5', 'start_at' => null, 'end_at' => null, 'priority' => 100, 'enable' => 1];
        $this->postJson('/api/admin/workspace/discounts', $discount)->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://cdnfly.example.test/v1/discounts' && $request->method() === 'POST' && $request->data() === $discount);
        $coupon = ['name' => 'Coupon', 'code' => 'WELCOME', 'coupon_type' => 'price', 'price_value' => 10, 'price_gt' => null, 'max_times' => null, 'persist_discount' => 1, 'cate' => 'package,traffic', 'start_at' => null, 'end_at' => null, 'enable' => 1];
        $this->putJson('/api/admin/workspace/coupons/8', $coupon)->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://cdnfly.example.test/v1/coupons/8' && $request->method() === 'PUT' && $request->data() === $coupon);
    }

    public function test_history_filters_and_read_only_group_options_are_forwarded(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/workspace/coupon-historys?uid=12&code=WELCOME&page=2&limit=10')->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/coupon-historys?') && $request['uid'] === '12' && $request['code'] === 'WELCOME' && $request['page'] === '2');
        $this->getJson('/api/admin/workspace/user-groups?limit=0')->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/user-groups?') && $request['limit'] === '0');
        $this->postJson('/api/admin/workspace/user-groups', ['name' => 'Not allowed'])->assertStatus(405);
        $this->deleteJson('/api/admin/workspace/coupon-historys/1')->assertStatus(405);
        Http::assertSentCount(2);
    }

    public function test_marketing_routes_require_an_administrator(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach (['discounts', 'coupons', 'coupon-historys', 'user-groups'] as $resource) {
            $this->getJson('/api/admin/workspace/'.$resource)->assertForbidden();
        }
        Http::assertNothingSent();
    }
}
