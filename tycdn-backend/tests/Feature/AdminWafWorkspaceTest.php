<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWafWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_preserves_empty_system_key_and_disabled_filter(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('listAllAcls')->once()->with(['page' => '1', 'limit' => '10', 'system_key' => '', 'enable' => '0'])->andReturn(['count' => 0, 'data' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/all-acls?page=1&limit=10&system_key=&enable=0')->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_waf_detail_and_subscription_updates_require_admin(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/acls/42')->assertForbidden();
        $this->postJson('/api/admin/acls/update-subscription', ['ids' => [42]])->assertForbidden();
    }

    public function test_waf_detail_preserves_rule_data_and_subscription_metadata(): void
    {
        $detail = ['data' => ['id' => 42, 'name' => 'Subscribed library', 'scope' => 'global', 'data' => '[{"action":"deny","matcher_groups":[]}]', 'subscribe_enable' => 1, 'subscribe_version' => 'v2']];
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/waf-rules/42')->andReturn($detail);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin/acls/42')->assertOk()->assertExactJson(['ok' => true, 'data' => $detail]);
    }

    public function test_subscription_update_validates_ids_and_forwards_only_selected_ids(): void
    {
        $result = ['data' => ['changed_count' => 1, 'unchanged_count' => 0, 'failed_count' => 1]];
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('POST', '/v1/waf-rules/update-subscription', ['ids' => [42, 43]])->andReturn($result);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([[], [0], [42, 42], ['invalid']] as $ids) {
            $this->postJson('/api/admin/acls/update-subscription', ['ids' => $ids])->assertUnprocessable();
        }
        $this->postJson('/api/admin/acls/update-subscription', ['ids' => [42, 43], 'url' => 'ignored'])->assertOk()->assertExactJson(['ok' => true, 'data' => $result]);
    }
}
