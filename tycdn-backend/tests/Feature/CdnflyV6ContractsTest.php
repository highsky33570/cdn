<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdnflyV6ContractsTest extends TestCase
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
    }

    public function test_disabled_api_keys_can_be_enabled_and_saved_for_only_the_mapped_user(): void
    {
        $user = User::factory()->create(['cdnfly_user_id' => 42]);
        Http::fake(['*/v1/api-key' => Http::response([
            'code' => 0, 'data' => ['api_key' => 'new-key', 'api_secret' => 'new-secret'],
        ])]);

        $this->actingAs($user)->postJson('/api/cdn/account/api-key', ['uid' => 999])
            ->assertOk()->assertJsonPath('data.data.api_key', 'new-key');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->data() === ['uid' => 42] && $r->hasHeader('api-key', 'master-key'));
        $this->assertSame('new-key', $user->fresh()->cdnfly_api_key);
        $this->assertSame('new-secret', $user->fresh()->cdnfly_api_secret);
        $this->assertNotSame('new-secret', $user->fresh()->getRawOriginal('cdnfly_api_secret'));
    }

    public function test_reset_saves_new_credentials_without_using_the_old_key(): void
    {
        $user = $this->customer();
        Http::fake(['*/v1/api-key*' => Http::response([
            'code' => 0, 'data' => ['api_key' => 'rotated', 'api_secret' => 'rotated-secret'],
        ])]);
        $this->actingAs($user)->putJson('/api/cdn/account/api-key', ['reset' => true])->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['reset'] === true && $r['uid'] === 42);
        $this->assertSame('rotated', $user->fresh()->cdnfly_api_key);
    }

    public function test_clearing_whitelist_sends_an_empty_string_and_reads_the_same_users_configuration(): void
    {
        $user = $this->customer();
        Http::fake(['*/v1/api-key*' => Http::sequence()
            ->push(['code' => 0, 'data' => ['api_key' => 'user-key', 'api_secret' => 'user-secret', 'api_ip' => '192.0.2.1']])
            ->push(['code' => 0, 'data' => null])
            ->push(['code' => 0, 'data' => ['api_key' => 'user-key', 'api_secret' => 'user-secret', 'api_ip' => '']])]);

        $this->actingAs($user)->putJson('/api/cdn/account/api-key', ['ip' => '', 'uid' => 999])
            ->assertOk()->assertJsonPath('data.data.api_ip', '');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->data() === ['ip' => '', 'uid' => 42]);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r['uid'] === 42);
    }

    public function test_disabling_api_access_clears_the_local_credentials_but_leaves_key_management_reachable(): void
    {
        $user = $this->customer();
        Http::fake(['*/v1/api-key*' => Http::response(['code' => 0, 'data' => null])]);
        $this->actingAs($user)->deleteJson('/api/cdn/account/api-key')->assertOk();
        $this->assertNull($user->fresh()->cdnfly_api_key);
        $this->assertNull($user->fresh()->cdnfly_api_secret);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === 'https://cdnfly.example.test/v1/api-key?uid=42');
        $this->actingAs($user->fresh())->getJson('/api/cdn/account/api-key')
            ->assertOk()->assertJsonPath('data.data', null);
    }

    public function test_a_failed_key_mutation_keeps_the_working_local_credentials(): void
    {
        $user = $this->customer();
        Http::fake(['*/v1/api-key*' => Http::sequence()
            ->push(['code' => 0, 'data' => ['api_key' => 'user-key', 'api_secret' => 'user-secret']])
            ->push(['code' => 'api_key-error', 'msg' => 'denied'])]);
        $this->actingAs($user)->deleteJson('/api/cdn/account/api-key')->assertStatus(500);
        $this->assertSame('user-key', $user->fresh()->cdnfly_api_key);
    }

    public function test_the_server_integration_key_cannot_be_disabled_from_account_settings(): void
    {
        $user = $this->customer();
        Http::fake(['*/v1/api-key*' => Http::response(['code' => 0, 'data' => ['api_key' => 'master-key']])]);
        $this->actingAs($user)->deleteJson('/api/cdn/account/api-key')->assertStatus(500);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_admin_rankings_use_master_credentials_and_preserve_the_native_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake(['*/v1/monitor/*/top*' => Http::response([
            'code' => 0, 'data' => [['res' => 'example.test', 'count' => 0, 'traffic' => 123, 'up_recv' => 0]],
        ])]);
        $this->actingAs($admin)->getJson('/api/admin/monitor/site-top?page=9&search=ignored')
            ->assertOk()->assertJsonPath('data.data.0.res', 'example.test');
        Http::assertSent(fn (Request $r) => $r->hasHeader('api-key', 'master-key')
            && $r->data() === ['type' => 'top-domain', 'recent_time' => '30m']);
        $this->actingAs($this->customer())->getJson('/api/admin/monitor/site-top')->assertForbidden();
    }

    public function test_admin_can_create_global_waf_rules_without_impersonating_a_user(): void
    {
        Http::fake(['*/v1/waf-rules' => Http::response(['code' => 0, 'data' => 71])]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/admin/acls', ['name' => 'Shared rule', 'scope' => 'global', 'data' => [], 'enable' => 1])
            ->assertCreated();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->hasHeader('api-key', 'master-key')
            && $r['scope'] === 'global' && $r['data'] === [] && ! isset($r['default_action']));
    }

    public function test_user_waf_libraries_map_the_owner_to_uid_and_keep_native_rule_data(): void
    {
        $rule = ['action' => 'block', 'matcher_groups' => [[['item' => 'uri', 'op' => '=', 'value' => '/private']]]];
        Http::fake(['*/v1/waf-rules' => Http::response(['code' => 0, 'data' => 72])]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/admin/acls', ['name' => 'Customer rule', 'scope' => 'user', 'user_id' => 42, 'data' => [$rule]])
            ->assertCreated();
        Http::assertSent(fn (Request $r) => $r['uid'] === 42 && $r['data'] === [$rule] && ! isset($r['user_id']));
    }

    public function test_forwarding_creation_requires_an_owner_and_sends_numeric_ports_in_arrays(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'user_package' => 8, 'listen' => [['protocol' => 'tcp', 'port' => 443]],
            'backend' => [['addr' => 'origin.example.test', 'weight' => 1, 'state' => 'up']], 'backend_port' => 8443,
        ];
        $this->actingAs($admin)->postJson('/api/admin/streams', $payload)->assertUnprocessable()->assertJsonValidationErrors('uid');
        Http::assertNothingSent();
        Http::fake(['*/v1/streams' => Http::response(['code' => 0, 'data' => '91'])]);
        $this->postJson('/api/admin/streams', $payload + ['uid' => 42])->assertOk();
        Http::assertSent(fn (Request $r) => $r['uid'] === 42 && $r['listen'][0]['port'] === 443 && is_array($r['backend']));
        $this->putJson('/api/admin/streams/91', ['listen' => json_encode($payload['listen'])])->assertUnprocessable();
    }

    public function test_node_edit_sends_native_enable_sort_and_bandwidth_fields(): void
    {
        Http::fake(['*/v1/nodes/3' => Http::response(['code' => 0, 'data' => null])]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson('/api/admin/nodes/3', ['enable' => 0, 'sort' => 12, 'bw_limit' => '1Gbps', 'target' => 'node', 'disable_by' => 'admin'])
            ->assertOk();
        Http::assertSent(fn (Request $r) => $r['enable'] === 0 && $r['sort'] === 12 && $r['bw_limit'] === '1Gbps' && ! isset($r['status']));
    }

    public function test_access_log_download_streams_binary_with_the_customers_credentials(): void
    {
        $gzip = gzencode('example access log');
        Http::fake(['*/v1/monitor/site/download-access-log/9' => Http::response($gzip, 200, ['Content-Type' => 'application/gzip'])]);
        $response = $this->actingAs($this->customer())->get('/api/cdn/access-log-downloads/9');
        $response->assertOk()->assertDownload('access-log-9.gz');
        $this->assertSame($gzip, $response->streamedContent());
        Http::assertSent(fn (Request $r) => $r->hasHeader('api-key', 'user-key') && ! $r->hasHeader('api-key', 'master-key'));
    }

    public function test_an_upstream_download_error_is_not_returned_as_a_gzip_file(): void
    {
        Http::fake(['*/v1/monitor/site/download-access-log/9' => Http::response(['code' => 'job-denied', 'msg' => 'not your job'])]);
        $this->actingAs($this->customer())->getJson('/api/cdn/access-log-downloads/9')
            ->assertStatus(500)->assertJsonPath('ok', false);
    }

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'user', 'cdnfly_user_id' => 42, 'cdnfly_api_key' => 'user-key', 'cdnfly_api_secret' => 'user-secret',
        ]);
    }

    public function test_admin_cc_routes_use_master_credentials_and_preserve_customer_ownership(): void
    {
        Http::fake(['*/v1/cc-matchs*' => Http::response(['code' => 0, 'data' => 4])]);
        $payload = ['uid' => 42, 'name' => 'URI matcher', 'data' => [['item' => 'uri', 'op' => '=', 'value' => '/test']]];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/admin/cc/matcher', $payload)->assertOk();
        Http::assertSent(fn (Request $r) => $r->hasHeader('api-key', 'master-key') && $r->data() === $payload);
        $this->actingAs($this->customer())->postJson('/api/admin/cc/matcher', $payload)->assertForbidden();
        $this->postJson('/api/cdn/proxy/v1/cc-matchs', $payload)->assertUnprocessable();
        Http::assertSentCount(1);
    }
}
