<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Why the settings page behaved as read-only, and how a config is actually
 * addressed.
 *
 * Two separate mistakes were stacked here. The save path filtered its payload
 * against a whitelist of key names — site_name, cache_time, default_node_group
 * — that were invented rather than read off CDNfly, whose rows are called
 * nginx-config-file, related-config-min-limit, block_page_num_limit… so every
 * field was dropped and the save always answered 「所有字段均被过滤」.
 *
 * Then the first fix addressed rows as PUT /v1/configs/{id}. CDNfly's config
 * rows carry no id at all, so every edit button was disabled. Per the v6 admin
 * reference, PUT /v1/configs keys on 作用域 + 类型 + 名称 and upserts one row.
 */
class AdminConfigEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_override_delete_preserves_the_category_and_region(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('proxyAdminRequest')->once()
            ->with('DELETE', '/v1/configs/region-7-cert_default_config-cert_default_type')
            ->andReturn(['code' => 0]);
        $this->actingAs($this->admin())
            ->deleteJson('/api/admin/configs/default-overrides/7/cert_default_config/cert_default_type')
            ->assertOk()->assertJsonPath('ok', true);
    }

    public function test_default_override_delete_rejects_global_and_unrelated_keys(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs($this->admin());
        foreach (['0/site_default_config/waf', '7/site/black-ip-limit', '7/cert_default_config/api_key'] as $target) {
            $this->deleteJson('/api/admin/configs/default-overrides/'.$target)->assertNotFound();
        }
    }

    public function test_resource_override_delete_uses_the_native_region_identity(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('proxyAdminRequest')->once()
            ->with('DELETE', '/v1/configs/region-7-site_stream-custom-port-allow')
            ->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->deleteJson('/api/admin/configs/resource-overrides/7/site_stream/custom-port-allow')
            ->assertOk()->assertJsonPath('ok', true);
    }

    public function test_resource_override_delete_rejects_global_or_unrelated_configuration(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs($this->admin());

        foreach (['0/site/black-ip-limit', '7/site/related-config-min-limit', '7/nginx_config/nginx-config-file'] as $target) {
            $this->deleteJson('/api/admin/configs/resource-overrides/'.$target)->assertNotFound();
        }
    }

    public function test_a_config_is_addressed_by_scope_type_and_name(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('upsertConfig')
            ->once()
            ->with([
                'name' => 'related-config-min-limit',
                'type' => 'site',
                'scope_name' => 'global',
                'scope_id' => 0,
                'value' => '2000',
                'enable' => 1,
            ])
            ->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs', [
                'name' => 'related-config-min-limit',
                'type' => 'site',
                'scope_name' => 'global',
                'scope_id' => 0,
                'value' => '2000',
                'enable' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * The identifying fields are what make the upsert land on the right row, so
     * a request without them must not reach CDNfly and quietly create a new one.
     */
    public function test_name_and_type_are_required(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('upsertConfig');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs', ['value' => '2000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    /**
     * CDNfly's real field names must survive — this is the regression that made
     * the page look read-only.
     */
    public function test_cdnflys_own_field_names_are_not_filtered_out(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('upsertConfig')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs', [
                'name' => 'nginx-config-file',
                'type' => 'site',
                'value' => '{"worker_processes":"auto"}',
            ])
            ->assertOk();

        $this->assertSame('nginx-config-file', $received['name']);
        $this->assertSame('{"worker_processes":"auto"}', $received['value']);
    }

    /**
     * Values are legitimately huge — the CAPTCHA templates are whole HTML
     * documents and nginx-config-file is a full config — so a large body must
     * not be rejected out of hand.
     */
    public function test_a_very_large_value_is_accepted(): void
    {
        $html = str_repeat('<div>x</div>', 5000);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('upsertConfig')->once()->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs', [
                'name' => 'easy_click_html',
                'type' => 'site',
                'value' => $html,
            ])
            ->assertOk();
    }

    /**
     * The credential blocklist guards something real and still applies.
     */
    public function test_credential_rows_stay_blocked(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('upsertConfig');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs', [
                'name' => 'smtp_password',
                'type' => 'site',
                'value' => 'hunter2',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_a_non_admin_cannot_edit_configs(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->putJson('/api/admin/configs', [
                'name' => 'x',
                'type' => 'site',
                'value' => 'y',
            ])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
