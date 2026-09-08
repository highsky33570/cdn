<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Why the settings page behaved as read-only.
 *
 * The bulk PUT /api/admin/configs filters its payload against a whitelist of key
 * names — site_name, cache_time, default_node_group and so on — that were
 * invented rather than read off CDNfly. The real rows are called
 * nginx-config-file, related-config-min-limit, block_page_num_limit… so every
 * field was dropped and the save answered "所有字段均被过滤", every time.
 *
 * Editing by id is the documented shape (PUT /v1/configs/{id}) and the one that
 * actually reaches CDNfly.
 */
class AdminConfigEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_real_config_row_can_be_edited_by_id(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateConfig')
            ->once()
            ->with(12, ['value' => '2000'])
            ->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs/12', [
                'name' => 'related-config-min-limit',
                'value' => '2000',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * The defect, pinned: the bulk endpoint rejects CDNfly's own field names.
     * If this ever starts passing, the whitelist has been fixed and the per-row
     * path is no longer the only way to save.
     */
    public function test_the_bulk_endpoint_rejects_cdnflys_real_field_names(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('updateConfigs');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs', [
                'related-config-min-limit' => '2000',
                'block_page_num_limit' => '200',
                'nginx-config-file' => '{}',
            ])
            ->assertStatus(422);
    }

    /**
     * Values are legitimately huge — the CAPTCHA templates are whole HTML
     * documents — so a large body must not be rejected out of hand.
     */
    public function test_a_very_large_value_is_accepted(): void
    {
        $html = str_repeat('<div>x</div>', 5000);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateConfig')->once()->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs/9', [
                'name' => 'easy_click_html',
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
        $cdnfly->shouldNotReceive('updateConfig');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/configs/3', [
                'name' => 'smtp_password',
                'value' => 'hunter2',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_a_non_admin_cannot_edit_configs(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->putJson('/api/admin/configs/12', ['name' => 'x', 'value' => 'y'])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
