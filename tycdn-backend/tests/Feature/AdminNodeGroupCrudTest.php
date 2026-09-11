<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Node groups are the unit a package is sold against: every customer on a
 * package shares the nodes in its group. So a reseller cannot configure a single
 * sellable package without being able to create one.
 *
 * The console already shipped the whole UI for this — dialogs, an API client,
 * create/edit/delete buttons — but the routes behind it did not exist, so every
 * one of those buttons returned 404 and the failure looked like a CDNfly
 * problem. These tests pin the routes to the payload the client actually sends.
 */
class AdminNodeGroupCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_node_group(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        // backup_switch_policy is deliberately absent: the panel does not send
        // it on create, and CDNfly rejects it with 数据类型错误 when it is.
        $cdnfly->shouldReceive('createNodeGroup')
            ->once()
            ->with([
                'name' => 'Asia Edge',
                'region_id' => 3,
                'des' => 'shared tier',
                'backup_switch_type' => 'interval',
            ])
            ->andReturn(['code' => 0, 'data' => 9]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/node-groups', [
                'name' => 'Asia Edge',
                'region_id' => 3,
                'des' => 'shared tier',
                'backup_switch_type' => 'interval',
                'backup_switch_policy' => '{"ip_num":2,"interval":60,"switch_order":"rand"}',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);
    }

    /**
     * validate() drops anything it has no rule for, so a field missing from the
     * rules never reaches CDNfly — the group would be created without its
     * failover policy and nothing would say so.
     */
    public function test_the_failover_policy_is_not_silently_dropped(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createNodeGroup')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/node-groups', [
                'name' => 'Asia Edge',
                'region_id' => 3,
                'backup_switch_type' => 'master_down',
                'backup_switch_policy' => '{}',
            ])
            ->assertCreated();

        $this->assertArrayHasKey('backup_switch_type', $received);
        $this->assertSame('master_down', $received['backup_switch_type']);
    }

    public function test_a_node_group_can_be_updated_and_deleted(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateNodeGroup')->once()->with(9, ['name' => 'Renamed'])->andReturn(['code' => 0]);
        $cdnfly->shouldReceive('deleteNodeGroup')->once()->with(9)->andReturn(['code' => 0]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/node-groups/9', ['name' => 'Renamed'])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson('/api/admin/node-groups/9')
            ->assertOk();
    }

    /**
     * An empty PUT would otherwise reach CDNfly as a no-op write.
     */
    public function test_an_update_with_no_fields_is_rejected_before_it_reaches_cdnfly(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('updateNodeGroup');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/node-groups/9', [])
            ->assertStatus(422);
    }

    public function test_creating_requires_a_name_and_a_region(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('createNodeGroup');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/node-groups', ['des' => 'no name, no region'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'region_id']);
    }

    /**
     * These routes carry the admin API key, so a customer reaching them would be
     * acting on the whole panel rather than their own account.
     */
    public function test_a_non_admin_cannot_touch_node_groups(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/api/admin/node-groups', ['name' => 'x', 'region_id' => 1])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson('/api/admin/node-groups/9')
            ->assertForbidden();
    }

    /**
     * Regions sit above node groups in the same chain, and shipped with the
     * same defect: a full UI over routes that were never registered.
     */
    public function test_regions_can_be_created_updated_and_deleted(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createRegion')->once()
            ->with(['name' => 'Asia', 'sort' => 1])->andReturn(['code' => 0]);
        $cdnfly->shouldReceive('updateRegion')->once()->with(4, ['name' => 'APAC'])->andReturn(['code' => 0]);
        $cdnfly->shouldReceive('deleteRegion')->once()->with(4)->andReturn(['code' => 0]);

        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/regions', ['name' => 'Asia', 'sort' => 1])->assertCreated();
        $this->actingAs($admin)->putJson('/api/admin/regions/4', ['name' => 'APAC'])->assertOk();
        $this->actingAs($admin)->deleteJson('/api/admin/regions/4')->assertOk();
    }

    public function test_a_non_admin_cannot_touch_regions(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson('/api/admin/regions', ['name' => 'x'])->assertForbidden();
    }

    /**
     * Verified against the v6 admin API reference for POST /v1/node-groups:
     * "未传 CNAME 主机名时服务端自动生成。可选 L2 配置必须与区域一致".
     * Both are optional upstream, so both have to survive validation rather than
     * being dropped as unknown keys.
     */
    public function test_documented_optional_node_group_fields_reach_cdnfly(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createNodeGroup')->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/node-groups', [
                'name' => 'Asia Edge',
                'region_id' => 3,
                'cname_hostname' => 'ng1.tycdn.org',
                'l2_config_id' => '7',
            ])
            ->assertCreated();

        $this->assertSame('ng1.tycdn.org', $received['cname_hostname']);
        // cast to an int, matching the panel's integerOrNull
        $this->assertSame(7, $received['l2_config_id']);
    }

    /**
     * The panel's create payload, captured from its own bundle
     * (chunk-579346f2): region_id, name, cname_hostname, des, sort,
     * backup_switch_type, l2_config_id — and notably NOT
     * backup_switch_policy, which only the edit endpoint accepts.
     *
     * Sending it on create is what CDNfly answered with 数据类型错误.
     */
    public function test_the_switch_policy_is_not_sent_on_create(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createNodeGroup')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/node-groups', [
                'name' => 'jp-shared',
                'region_id' => 1,
                'des' => '',
                'backup_switch_type' => 'master_down',
                'backup_switch_policy' => '{}',
            ])
            ->assertCreated();

        $this->assertArrayNotHasKey('backup_switch_policy', $received);
        $this->assertSame('master_down', $received['backup_switch_type']);
        $this->assertSame(1, $received['region_id']);
    }

    /**
     * Edit does take it, so the field must survive there.
     */
    public function test_the_switch_policy_is_kept_on_edit(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateNodeGroup')
            ->once()
            ->andReturnUsing(function (int $id, array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->putJson('/api/admin/node-groups/9', [
                'backup_switch_type' => 'interval',
                'backup_switch_policy' => '{"ip_num":2,"interval":60,"switch_order":"rand"}',
            ])
            ->assertOk();

        $this->assertSame(
            '{"ip_num":2,"interval":60,"switch_order":"rand"}',
            $received['backup_switch_policy'],
        );
    }

    /**
     * sort and l2_config_id are integers upstream (the panel runs them through
     * integerOrNull), not the strings this once declared.
     */
    public function test_sort_and_l2_config_are_integers(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createNodeGroup')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/node-groups', [
                'name' => 'jp-shared',
                'region_id' => 1,
                'sort' => 100,
                'l2_config_id' => 7,
            ])
            ->assertCreated();

        $this->assertSame(100, $received['sort']);
        $this->assertSame(7, $received['l2_config_id']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
