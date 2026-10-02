<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CDNfly refuses to delete an enabled certificate ("请先禁用再删除"), and a bare
 * PUT /v1/certs/{id} with enable=0 often returns success without changing state.
 * Deletion must disable via the batch contract, verify, then DELETE.
 */
class AdminCertDisableDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cdnfly.base_url' => 'https://panel.example.test',
            'services.cdnfly.admin_api_key' => 'k',
            'services.cdnfly.admin_api_secret' => 's',
            'services.cdnfly.outbound_enabled' => true,
        ]);
    }

    public function test_delete_disables_via_batch_put_then_deletes(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'GET' && str_ends_with($url, '/v1/certs/3')) {
                static $gets = 0;
                $gets++;

                // First reads: still enabled. After the batch PUT: disabled.
                $enable = $gets <= 2 ? 1 : 0;

                return Http::response([
                    'code' => 0,
                    'data' => ['id' => 3, 'enable' => $enable, 'version' => $gets],
                ]);
            }

            if ($method === 'PUT' && str_ends_with($url, '/v1/certs')) {
                return Http::response([
                    'code' => 0,
                    'data' => null,
                    'msg' => '更新证书成功',
                ]);
            }

            if ($method === 'DELETE' && str_ends_with($url, '/v1/certs/3')) {
                return Http::response([
                    'code' => 0,
                    'data' => '',
                    'msg' => '证书删除成功',
                ]);
            }

            return Http::response(['code' => 'unexpected', 'msg' => "{$method} {$url}"], 500);
        });

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->deleteJson('/api/admin/all-certs/3')
            ->assertOk()
            ->assertJsonPath('ok', true);

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT' || $request->url() !== 'https://panel.example.test/v1/certs') {
                return false;
            }
            $data = $request->data();

            return isset($data[0]['id'], $data[0]['enable'], $data[0]['version'])
                && $data[0]['id'] === 3
                && $data[0]['enable'] === 0
                && $data[0]['version'] === 2;
        });
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://panel.example.test/v1/certs/3');
    }

    public function test_delete_fails_clearly_when_disable_does_not_stick(): void
    {
        Http::fake([
            'https://panel.example.test/v1/certs/3' => Http::response([
                'code' => 0,
                'data' => ['id' => 3, 'enable' => 1, 'version' => 1],
            ]),
            'https://panel.example.test/v1/certs' => Http::response([
                'code' => 0,
                'data' => null,
                'msg' => '更新证书成功',
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->deleteJson('/api/admin/all-certs/3')
            ->assertStatus(500)
            ->assertJsonPath('ok', false);

        $this->assertStringContainsString('证书状态未变更', (string) $response->json('message'));
        Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
    }

    public function test_create_rejects_soft_success_with_zero_id(): void
    {
        Http::fake([
            'https://panel.example.test/v1/certs' => Http::response([
                'code' => 0,
                'data' => '0',
                'msg' => '证书添加成功',
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/admin/all-certs', [
                'name' => 'demo',
                'type' => 'custom',
                'cert' => 'PEM',
                'key' => 'KEY',
            ])
            ->assertStatus(500);

        $this->assertStringContainsString('未返回有效证书 ID', (string) $response->json('message'));
    }

    public function test_enable_toggle_uses_batch_put_and_version(): void
    {
        Http::fake([
            'https://panel.example.test/v1/certs/5' => Http::sequence()
                ->push(['code' => 0, 'data' => ['id' => 5, 'enable' => 1, 'version' => 4]])
                ->push(['code' => 0, 'data' => ['id' => 5, 'enable' => 0, 'version' => 5]]),
            'https://panel.example.test/v1/certs' => Http::response([
                'code' => 0,
                'msg' => '更新证书成功',
            ]),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson('/api/admin/all-certs/5', ['enable' => 0])
            ->assertOk();

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT' || $request->url() !== 'https://panel.example.test/v1/certs') {
                return false;
            }
            $data = $request->data();

            return ($data[0] ?? null) === ['enable' => 0, 'version' => 4, 'id' => 5];
        });
    }
}
