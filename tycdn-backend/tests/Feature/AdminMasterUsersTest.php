<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMasterUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cdnfly.base_url' => 'https://master.example.test', 'services.cdnfly.admin_api_key' => 'admin-key', 'services.cdnfly.admin_api_secret' => 'admin-secret', 'services.cdnfly.outbound_enabled' => true]);
        Http::preventStrayRequests();
    }

    public function test_reads_use_native_ids_and_filter_passwords_and_tokens(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $row = ['id' => 87, 'name' => 'native', 'balance' => 1234, 'password' => 'hash-must-not-leak', 'api_secret' => 'secret', 'access_token' => 'token'];
        Http::fake(['*/v1/users/87*' => Http::response(['code' => 0, 'data' => $row]), '*' => Http::response(['code' => 0, 'count' => 1, 'data' => [$row]])]);
        $this->getJson('/api/admin/master-users?user_id=87&name=native&cert_verified=1&user_group=4&page=2&limit=10&token=1')->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.data.0.balance', 1234)->assertJsonMissingPath('data.data.0.password')->assertJsonMissingPath('data.data.0.api_secret');
        Http::assertSent(fn ($r) => $r['user_id'] === '87' && $r['page'] === '2' && ! isset($r['token']));
        $this->getJson('/api/admin/master-users/87?token=1')->assertOk()->assertJsonPath('data.data.id', 87)->assertJsonMissingPath('data.data.password')->assertJsonMissingPath('data.data.access_token');
        Http::assertSent(fn ($r) => $r->url() === 'https://master.example.test/v1/users/87' && $r->data() === []);
    }

    public function test_create_and_partial_edit_preserve_native_fields_without_changing_local_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Http::fake(['*' => Http::response(['code' => 0, 'data' => 87])]);
        $this->postJson('/api/admin/master-users', ['email' => 'new@example.test', 'name' => 'newuser', 'password' => 'Example123', 'type' => 2, 'user_group' => null, 'enable' => 1, 'role' => 'admin', 'balance' => 999])->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://master.example.test/v1/users' && $r['type'] === 2 && ! isset($r['role']) && ! isset($r['balance']));
        $this->putJson('/api/admin/master-users/87', ['des' => '', 'login_captcha' => '', 'white_ip' => '', 'user_group' => null, 'enable' => 0, 'type' => 1])->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === 'https://master.example.test/v1/users/87' && $r->data() === ['user_group' => null, 'login_captcha' => '', 'enable' => 0, 'des' => '', 'white_ip' => '']);
        $this->assertDatabaseCount('users', 1);
        $this->assertEquals('admin', $admin->fresh()->role);
    }

    public function test_validation_rejects_invalid_user_fields_before_forwarding(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/master-users', ['email' => 'bad', 'name' => '', 'password' => 'short', 'type' => 5])->assertUnprocessable()->assertJsonValidationErrors(['email', 'name', 'password', 'type']);
        $this->putJson('/api/admin/master-users/87', ['enable' => 5, 'login_captcha' => 'unknown', 'auth2_end_at' => 'invalid'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_groups_and_delete_use_fixed_native_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['code' => 0, 'data' => [], 'count' => 0])]);
        $this->getJson('/api/admin/master-user-groups?limit=0')->assertOk();
        $this->postJson('/api/admin/master-user-groups', ['name' => 'VIP', 'des' => ''])->assertOk();
        $this->putJson('/api/admin/master-user-groups/8', ['name' => 'Updated', 'des' => '', 'sort' => 3])->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === 'https://master.example.test/v1/user-groups/8' && $r->data() === ['name' => 'Updated', 'des' => '']);
        $this->deleteJson('/api/admin/master-user-groups/8')->assertOk();
        $this->deleteJson('/api/admin/master-users/87')->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->url() === 'https://master.example.test/v1/users/87');
    }

    public function test_native_switch_is_explicit_post_and_does_not_replace_local_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Http::fake(['*/v1/configs/*' => Http::response(['code' => 0, 'data' => ['value' => 'users.example.test']]), '*' => Http::response(['code' => 0, 'data' => ['access_token' => 'test/token=', 'username' => 'native', 'uid' => 87, 'type' => 2]])]);
        $response = $this->postJson('/api/admin/master-users/87/switch')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('https://users.example.test/dashboard/login?', $response->json('data.url'));
        Http::assertSent(fn ($r) => $r->method() === 'GET' && ($r['token'] ?? null) === 1);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_all_native_user_endpoints_require_an_administrator(): void
    {
        $this->getJson('/api/admin/master-users')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach ([['get', '/master-users'], ['post', '/master-users'], ['put', '/master-users/87'], ['delete', '/master-users/87'], ['post', '/master-users/87/switch'], ['get', '/master-user-groups'], ['post', '/master-user-groups'], ['put', '/master-user-groups/8'], ['delete', '/master-user-groups/8']] as [$method, $url]) {
            $this->{$method.'Json'}('/api/admin'.$url)->assertForbidden();
        }
        Http::assertNothingSent();
    }
}
