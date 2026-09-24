<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserAccountLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cdnfly.base_url' => 'https://cdnfly.example.test',
            'services.cdnfly.admin_api_key' => 'master-key',
            'services.cdnfly.admin_api_secret' => 'master-secret',
            'services.cdnfly.outbound_enabled' => true,
        ]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create([
            'role' => 'user', 'cdnfly_api_key' => 'customer-key', 'cdnfly_api_secret' => 'customer-secret',
        ]));
    }

    public function test_both_tabs_use_customer_credentials_and_forward_native_filters(): void
    {
        Http::fake(['*/v1/log/*' => Http::response(['code' => 0, 'count' => 1, 'data' => [['ip' => '192.0.2.1']]])]);
        foreach ([
            'login' => ['success' => '0'],
            'op' => ['action' => '更新', 'type' => 'sites', 'content' => 'example.test', 'diff' => 'backend'],
        ] as $tab => $filters) {
            $query = $filters + ['page' => '1', 'limit' => '10', 'start' => '2026-09-24', 'end' => '2026-09-25', 'ip' => '192.0.2.1'];
            $this->getJson('/api/cdn/proxy/v1/log/'.$tab.'?'.http_build_query($query))
                ->assertOk()->assertJsonPath('data.data.0.ip', '192.0.2.1');
            Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/log/'.$tab.'?')
                && $request->method() === 'GET' && $request->data() === $query
                && $request->hasHeader('api-key', 'customer-key')
                && $request->hasHeader('api-secret', 'customer-secret'));
        }
        Http::assertSentCount(2);
    }

    public function test_operation_logs_are_read_only_and_cannot_override_customer_scope(): void
    {
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}('/api/cdn/proxy/v1/log/op', [])->assertForbidden();
        }
        $this->getJson('/api/cdn/proxy/v1/log/op?uid=1')->assertUnprocessable();
        $this->getJson('/api/cdn/proxy/v1/log/backup')->assertForbidden();
        Http::assertNothingSent();
    }
}
