<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/v1/configs/global-0-error_page-error-page';

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_routes_require_an_administrator(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/error-pages')->assertForbidden();
        $this->putJson('/api/admin/error-pages', ['patch' => ['p400' => 'test']])->assertForbidden();
        $this->getJson('/api/admin/error-pages/overrides')->assertForbidden();
        $this->putJson('/api/admin/error-pages/overrides/node/2', ['patch' => ['p400' => 'test']])->assertForbidden();
        $this->deleteJson('/api/admin/error-pages/overrides/node/2')->assertForbidden();
    }

    public function test_read_retains_native_template_keys(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)
            ->andReturn(['data' => ['value' => '{"p403":"<h1>Forbidden</h1>","p456":"Blocked"}']]);
        $this->admin();
        $this->getJson('/api/admin/error-pages')->assertOk()->assertJsonPath('data.p403', '<h1>Forbidden</h1>')->assertJsonMissingPath('data.waf_block');
    }

    public function test_patch_preserves_html_whitespace_other_templates_and_unknown_members(): void
    {
        $html = " \n<!DOCTYPE html>\n<script>window.example = '$1';</script>\n  ";
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => '{"p400":"old","p403":"keep","future":{"value":7}}']]);
        $api->shouldReceive('proxyAdminRequest')->once()->withArgs(function ($method, $path, $body) use ($html) {
            $this->assertSame(['p400' => $html, 'p403' => 'keep', 'future' => ['value' => 7]], json_decode($body['value'], true));

            return $method === 'PUT' && $path === self::PATH;
        })->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/error-pages', ['patch' => ['p400' => $html]])->assertOk();
    }

    public function test_empty_global_template_is_still_a_string(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => '{"p400":"old"}']]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', self::PATH, ['value' => '{"p400":""}'])->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/error-pages', ['patch' => ['p400' => '']])->assertOk();
    }

    public function test_invalid_fields_and_scope_cannot_reach_the_master(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('proxyAdminRequest');
        $this->admin();
        foreach ([['unknown' => 'x'], ['p400' => ['html' => 'x']], ['p400' => str_repeat('x', 200001)]] as $patch) {
            $this->putJson('/api/admin/error-pages', ['patch' => $patch])->assertUnprocessable();
        }
        $this->putJson('/api/admin/error-pages', ['patch' => ['p400' => 'x'], 'remove' => ['p403']])->assertUnprocessable();
        $this->putJson('/api/admin/error-pages/overrides/node/2', ['patch' => ['waf_block' => 'x']])->assertUnprocessable();
        $this->putJson('/api/admin/error-pages/overrides/node/2', ['patch' => ['p400' => '']])->assertUnprocessable();
        $this->deleteJson('/api/admin/error-pages/overrides/global/0')->assertNotFound();
    }

    public function test_failed_or_malformed_reads_never_write_replacements(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andThrow(new \RuntimeException('Unavailable'));
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', self::PATH)->andReturn(['data' => ['value' => '[]']]);
        $this->admin();
        $this->putJson('/api/admin/error-pages', ['patch' => ['p400' => 'x']])->assertStatus(500);
        $this->putJson('/api/admin/error-pages', ['patch' => ['p400' => 'x']])->assertStatus(500);
    }

    public function test_override_listing_uses_only_error_page_scopes(): void
    {
        $this->mock(CdnflyApiService::class)->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs', ['page' => '2', 'limit' => '10', 'type' => 'error_page', 'name' => 'error-page', 'scope_name' => 'region,node'])->andReturn(['count' => 11, 'data' => []]);
        $this->admin();
        $this->getJson('/api/admin/error-pages/overrides?page=2&limit=10&type=other&scope_name=global')->assertOk()->assertJsonPath('data.count', 11);
    }

    public function test_override_patch_and_removal_preserve_other_templates(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->once()->with('GET', '/v1/configs', ['type' => 'error_page', 'name' => 'error-page', 'scope_name' => 'node', 'scope_id' => 3])->andReturn(['data' => [['scope_name' => 'node', 'scope_id' => 3, 'value' => '{"p400":"remove","p403":"keep","future":"keep"}']]]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/configs/node-3-error_page-error-page', ['value' => '{"p403":"keep","future":"keep","p502":"new"}'])->andReturn(['code' => 0]);
        $this->admin();
        $this->putJson('/api/admin/error-pages/overrides/node/3', ['patch' => ['p502' => 'new'], 'remove' => ['p400']])->assertOk();
    }

    public function test_create_duplicate_missing_edit_and_scoped_delete(): void
    {
        $api = $this->mock(CdnflyApiService::class);
        $api->shouldReceive('proxyAdminRequest')->times(3)->with('GET', '/v1/configs', ['type' => 'error_page', 'name' => 'error-page', 'scope_name' => 'region', 'scope_id' => 2])->andReturn(['data' => []], ['data' => [['scope_name' => 'region', 'scope_id' => 2, 'value' => '{"p400":"html"}']]], ['data' => []]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('PUT', '/v1/configs/region-2-error_page-error-page', ['value' => '{"p400":"html"}'])->andReturn(['code' => 0]);
        $api->shouldReceive('proxyAdminRequest')->once()->with('DELETE', '/v1/configs/region-2-error_page-error-page')->andReturn(['code' => 0]);
        $this->admin();
        $body = ['patch' => ['p400' => 'html'], 'creating' => true];
        $this->putJson('/api/admin/error-pages/overrides/region/2', $body)->assertOk();
        $this->putJson('/api/admin/error-pages/overrides/region/2', $body)->assertConflict();
        $this->putJson('/api/admin/error-pages/overrides/region/2', ['patch' => ['p400' => 'html']])->assertNotFound();
        $this->deleteJson('/api/admin/error-pages/overrides/region/2')->assertOk();
    }
}
