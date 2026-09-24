<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOrderRecordsTest extends TestCase
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
        Http::preventStrayRequests();
    }

    public function test_creation_preserves_cents_master_uid_and_native_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'data' => null])]);
        $payload = ['uid' => 42, 'type' => '购买', 'des' => '录入订单', 'create_at' => '2026-09-24 08:00:00', 'pay_at' => '2026-09-24 08:05:00', 'amount' => 1234, 'real_amount' => 999, 'pay_type' => '支付宝', 'mch_order_no' => 'M-42', 'transaction_id' => 'TX-42', 'state' => '已付款'];
        $this->postJson('/api/admin/finance/orders', [...$payload, 'untrusted' => 'ignored'])->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://cdnfly.example.test/v1/orders' && $request->data() == $payload && $request['amount'] === 1234 && $request['real_amount'] === 999 && $request['uid'] === 42);
    }

    public function test_partial_edit_preserves_omitted_fields_and_can_clear_optional_text(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'data' => null])]);
        $this->putJson('/api/admin/finance/orders/7', ['real_amount' => 0, 'des' => '', 'transaction_id' => '', 'id' => 999])->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->url() === 'https://cdnfly.example.test/v1/orders/7' && $request->data() === ['real_amount' => 0, 'des' => '', 'transaction_id' => '']);
    }

    public function test_invalid_money_and_required_fields_do_not_reach_master(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/finance/orders', [])->assertUnprocessable()->assertJsonValidationErrors(['uid', 'type', 'create_at', 'pay_type']);
        $this->putJson('/api/admin/finance/orders/7', ['amount' => 1.5, 'real_amount' => 'abc', 'state' => 'unknown', 'uid' => -1, 'create_at' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['amount', 'real_amount', 'state', 'uid', 'create_at']);
        Http::assertNothingSent();
    }

    public function test_delete_and_master_business_errors_use_existing_error_handling(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::sequence()->push(['code' => 0, 'data' => null])->push(['code' => 'order-1', 'msg' => '订单不存在', 'data' => null])]);
        $this->deleteJson('/api/admin/finance/orders/7')->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://cdnfly.example.test/v1/orders/7');
        $this->putJson('/api/admin/finance/orders/7', ['des' => 'changed'])->assertStatus(500)->assertJsonPath('ok', false);
    }

    public function test_order_mutations_require_admin_and_reads_preserve_count_and_money_total(): void
    {
        $this->postJson('/api/admin/finance/orders', [])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson('/api/admin/finance/orders', [])->assertForbidden();
        $this->putJson('/api/admin/finance/orders/7', [])->assertForbidden();
        $this->deleteJson('/api/admin/finance/orders/7')->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'count' => 2, 'total' => 123400, 'data' => [['id' => 7, 'amount' => 1234]]])]);
        $this->getJson('/api/admin/workspace/master-orders?uid=42&type=购买&state=已付款&start=2026-09-01&end=2026-09-25&page=2&limit=10')->assertOk()->assertJsonPath('data.count', 2)->assertJsonPath('data.total', 123400);
        Http::assertSent(fn ($request) => $request['uid'] === '42' && $request['type'] === '购买' && $request['state'] === '已付款' && $request['end'] === '2026-09-25' && $request['page'] === '2');
    }
}
