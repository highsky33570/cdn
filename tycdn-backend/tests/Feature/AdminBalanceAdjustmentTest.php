<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBalanceAdjustmentTest extends TestCase
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

    public function test_credit_and_deduction_preserve_master_user_decimal_and_remark(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'cdnfly_user_id' => 999]));
        Http::fake(['*' => Http::response(['code' => 0, 'msg' => '操作成功', 'data' => null])]);
        foreach (['add', 'reduce'] as $type) {
            $this->postJson('/api/admin/finance/recharge', ['uid' => '42', 'type' => $type, 'amount' => '10.05', 'des' => '服务费用调整', 'role' => 'admin'])->assertOk()->assertJsonPath('ok', true);
            Http::assertSent(fn ($request) => $request->url() === 'https://cdnfly.example.test/v1/user/42/recharge'
                && $request->method() === 'POST'
                && $request->data() === ['type' => $type, 'amount' => '10.05', 'des' => '服务费用调整']);
        }
        Http::assertSentCount(2);
    }

    public function test_invalid_amount_type_and_user_are_rejected_before_any_master_request(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['-1', '0', '1.001', '1e3', 'bad', 5] as $amount) {
            $this->postJson('/api/admin/finance/recharge', ['uid' => 42, 'type' => 'add', 'amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->postJson('/api/admin/finance/recharge', ['uid' => 0, 'type' => 'other', 'amount' => '1'])->assertUnprocessable()->assertJsonValidationErrors(['uid', 'type']);
        Http::assertNothingSent();
    }

    public function test_optional_remark_uses_native_default_and_master_errors_are_not_success(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::sequence()->push(['code' => 0, 'data' => null])->push(['code' => 'user_recharge-1', 'msg' => '指定用户不存在。', 'data' => ''])]);
        $payload = ['uid' => 42, 'type' => 'reduce', 'amount' => '0.01', 'des' => ''];
        $this->postJson('/api/admin/finance/recharge', $payload)->assertOk();
        Http::assertSent(fn ($request) => $request['des'] === '' && $request['amount'] === '0.01');
        $this->postJson('/api/admin/finance/recharge', $payload)->assertStatus(500)->assertJsonPath('ok', false);
    }

    public function test_balance_adjustments_require_an_administrator(): void
    {
        $payload = ['uid' => 42, 'type' => 'reduce', 'amount' => '10'];
        $this->postJson('/api/admin/finance/recharge', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson('/api/admin/finance/recharge', $payload)->assertForbidden();
        Http::assertNothingSent();
    }
}
