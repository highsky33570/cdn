<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTrafficPackagesTest extends TestCase
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

    public function test_traffic_editor_and_status_requests_reach_native_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['name' => 'Traffic boost', 'des' => 'Extra traffic', 'amount' => '2TB', 'bind_package' => '1,2', 'valid_days' => 30, 'price' => 9, 'enable' => 1];
        $this->postJson('/api/admin/workspace/traffic-packages', $payload)->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://cdnfly.example.test/v1/traffic-packages' && $request->data() === $payload);

        $batch = [['id' => 1, 'enable' => 0], ['id' => 2, 'enable' => 0]];
        $this->putJson('/api/admin/workspace/traffic-packages', $batch)->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->url() === 'https://cdnfly.example.test/v1/traffic-packages' && $request->data() === $batch);

        $sold = [['id' => 21, 'enable' => 0, 'reason' => '其他']];
        $this->putJson('/api/admin/workspace/user-traffic-packages', $sold)->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->url() === 'https://cdnfly.example.test/v1/user-traffic-packages' && $request->data() === $sold);
        $this->getJson('/api/admin/workspace/user-traffic-packages?uid=6&traffic_package_id=1&enable=0&page=2&limit=10')->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/v1/user-traffic-packages?') && $request['uid'] === '6' && $request['enable'] === '0' && $request['traffic_package_id'] === '1');
    }

    public function test_traffic_management_is_administrator_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/workspace/traffic-packages')->assertForbidden();
        $this->putJson('/api/admin/workspace/user-traffic-packages', [['id' => 21, 'enable' => 1]])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_empty_notes_and_package_restrictions_are_forwarded_as_strings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/workspace/traffic-packages/1', ['des' => '', 'bind_package' => ''])->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://cdnfly.example.test/v1/traffic-packages/1' && $request->data() === ['des' => '', 'bind_package' => '']);
        $this->putJson('/api/admin/workspace/user-traffic-packages', [['id' => 21, 'bind_package' => '']])->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://cdnfly.example.test/v1/user-traffic-packages' && $request->data() === [['id' => 21, 'bind_package' => '']]);
    }
}
