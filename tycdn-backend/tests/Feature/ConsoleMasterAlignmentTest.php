<?php

namespace Tests\Feature;

use App\Models\ServiceInstance;
use App\Models\User;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use App\Support\SitePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleMasterAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_services_are_not_counted_as_failed_orders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        ServiceInstance::create(['user_id' => $user->id, 'status' => 'failed']);
        $this->actingAs($user)->getJson('/api/admin/finance/summary')->assertOk()
            ->assertJsonPath('data.orders_failed', 0)->assertJsonPath('data.services_failed', 1);
    }

    public function test_secret_masks_round_trip_without_changing_empty_objects_or_other_fields(): void
    {
        $original = '{"smtp_password":"private-value","host":"old.example","empty":{},"list":[]}';
        $masked = ConfigSecrets::mask(['name' => 'smtp_config', 'value' => $original]);
        $this->assertStringNotContainsString('private-value', $masked['value']);
        $this->assertStringContainsString('"empty":{}', $masked['value']);
        $updated = str_replace('old.example', 'new.example', $masked['value']);
        $this->assertSame(str_replace('old.example', 'new.example', $original), ConfigSecrets::restore($updated, $original));
    }

    public function test_settings_endpoint_masks_and_restores_secrets_using_the_current_scope(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $row = ['name' => 'smtp_config', 'type' => 'system', 'scope_name' => 'global', 'scope_id' => 0, 'value' => '{"password":"stored-secret","host":"old"}'];
        $cdnfly->shouldReceive('getConfigs')->twice()->andReturn(['code' => 0, 'data' => [$row]]);
        $cdnfly->shouldReceive('upsertConfig')->once()->withArgs(function (array $payload): bool {
            return json_decode($payload['value'], true) === ['password' => 'stored-secret', 'host' => 'new'];
        })->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->getJson('/api/admin/configs')->assertOk();
        $this->assertStringNotContainsString('stored-secret', $response->getContent());
        $this->putJson('/api/admin/configs', [...$row, 'value' => json_encode(['password' => ConfigSecrets::MASK, 'host' => 'new'])])->assertOk();
    }

    public function test_site_updates_preserve_origin_weights_and_unknown_nested_values(): void
    {
        $payload = ['backend' => [['addr' => '192.0.2.1', 'weight' => 5, 'state' => 'up', 'future_option' => 'kept'], ['addr' => '192.0.2.2', 'weight' => 2, 'state' => 'down']], 'https_listen' => ['cert' => 4, 'port' => '443', 'http3' => 1, 'future_option' => 'kept']];
        $this->mock(CdnflyApiService::class)->shouldReceive('updateAdminSite')->once()->with(9, $payload)->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/admin/sites/9', $payload)->assertOk();
        $serialized = json_encode(SitePayload::normalize(['https_listen' => [], 'backend' => []]));
        $this->assertSame('{"https_listen":{},"backend":[]}', $serialized);
    }

    public function test_workspace_enforces_admin_scope_and_fixed_resource_methods(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create())->getJson('/api/admin/workspace/tasks')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/workspace/unknown')->assertNotFound();
        $this->postJson('/api/admin/workspace/overview', [])->assertStatus(405);
        $this->deleteJson('/api/admin/workspace/tasks/1')->assertStatus(405);
    }

    public function test_task_cancellation_uses_master_enable_flag(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()
            ->with('PUT', '/v1/tasks/12', ['enable' => 0])->andReturn(['code' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson('/api/admin/workspace/tasks/12', ['enable' => 0, 'state' => 'cancelled'])->assertOk();
    }

    public function test_mapping_preview_does_not_create_or_bind_accounts(): void
    {
        $user = User::factory()->create(['cdnfly_user_id' => null]);
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('findUserByEmail')->once()->with($user->email)->andReturn(['id' => 7, 'username' => 'existing', 'email' => $user->email]);
        $cdnfly->shouldNotReceive('createCdnflyUser');
        $cdnfly->shouldNotReceive('enableUserApiKey');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/users/'.$user->id.'/mapping-preview')->assertOk()->assertJsonPath('data.candidate.id', 7);
        $this->assertNull($user->fresh()->cdnfly_user_id);
    }

    public function test_recovery_cannot_map_a_master_administrator(): void
    {
        $user = User::factory()->create(['cdnfly_user_id' => null]);
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('findUserByEmail')->andReturn(['id' => 1, 'username' => 'admin', 'email' => $user->email]);
        $cdnfly->shouldReceive('proxyAdminRequest')->with('GET', '/v1/users/1')->andReturn(['data' => ['id' => 1, 'type' => 1]]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/admin/users/'.$user->id.'/mapping', ['cdnfly_user_id' => 1])->assertUnprocessable();
        $this->assertNull($user->fresh()->cdnfly_user_id);
    }

    public function test_mapping_reuses_existing_remote_user_and_discards_stale_credentials(): void
    {
        $user = User::factory()->create(['cdnfly_user_id' => null, 'cdnfly_api_key' => 'old-key', 'cdnfly_api_secret' => 'old-secret']);
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('findUserByEmail')->once()->with($user->email)->andReturn(['id' => 7, 'email' => $user->email]);
        $cdnfly->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/users/7')->andReturn(['data' => ['id' => 7, 'type' => 2]]);
        $cdnfly->shouldNotReceive('createCdnflyUser');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/admin/users/'.$user->id.'/mapping', ['cdnfly_user_id' => 7])->assertOk();
        $this->assertSame(7, (int) $user->fresh()->cdnfly_user_id);
        $this->assertNull($user->fresh()->cdnfly_api_key);
        $this->assertNull($user->fresh()->cdnfly_api_secret);
    }

    public function test_mapping_cannot_bind_an_account_already_owned_by_another_local_user(): void
    {
        User::factory()->create(['cdnfly_user_id' => 7]);
        $user = User::factory()->create(['cdnfly_user_id' => null]);
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('findUserByEmail')->once()->andReturn(['id' => 7, 'email' => $user->email]);
        $cdnfly->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/users/7')->andReturn(['data' => ['id' => 7, 'type' => 2]]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/admin/users/'.$user->id.'/mapping', ['cdnfly_user_id' => 7])->assertUnprocessable();
        $this->assertNull($user->fresh()->cdnfly_user_id);
    }
}
