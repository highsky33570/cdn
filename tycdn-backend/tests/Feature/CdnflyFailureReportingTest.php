<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An upstream CDNfly failure must reach the operator intact.
 *
 * The console sits behind Cloudflare, and Cloudflare *intercepts* an origin 502:
 * it throws away the body and serves its own page instead —
 *
 *   {"title":"Error 502: Bad gateway",
 *    "detail":"The origin web server returned an invalid or incomplete
 *              response ... the origin is overloaded or misconfigured"}
 *
 * So every CDNfly rejection arrived looking like the portal's own server was
 * broken, and the real reason never left the building. Initialising a node
 * failed with "origin is overloaded" when the origin was perfectly healthy and
 * CDNfly had simply refused the request.
 *
 * 500 is passed through untouched. That matters more here than 502 being the
 * more literally correct status for a gateway error: a status nobody can read
 * the body of is worse than a slightly less precise one.
 */
class CdnflyFailureReportingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The exact request from the bug report: POST /api/admin/nodes.
     */
    public function test_an_upstream_failure_is_not_reported_as_a_gateway_error(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createNode')
            ->once()
            ->andThrow(new \RuntimeException('CDNfly create node failed: 区域不存在'));

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/nodes', [
                'pending_node_id' => 1,
                'region_id' => 1,
                'name' => 'jp-tokyo-01',
                'des' => '',
                'type' => 'L1',
            ]);

        // 502 and 504 are the two Cloudflare replaces with its own page.
        $this->assertNotSame(502, $response->status(), 'Cloudflare would eat the body');
        $this->assertNotSame(504, $response->status());
        $response->assertStatus(500);
    }

    /**
     * "CDNfly 通讯失败" alone is useless — a missing region, a duplicate name and
     * an expired licence all look the same. The upstream text is the whole point.
     */
    public function test_cdnflys_own_message_reaches_the_client(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createNode')
            ->andThrow(new \RuntimeException('CDNfly create node failed: 区域不存在'));

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/nodes', [
                'pending_node_id' => 1,
                'region_id' => 1,
                'name' => 'jp-tokyo-01',
                'type' => 'L1',
            ])->assertStatus(500);

        $this->assertStringContainsString('区域不存在', $response->json('message'));
        $this->assertStringContainsString('区域不存在', (string) $response->json('upstream_error'));
    }

    /**
     * A page firing several requests at once needs to know which one broke.
     */
    public function test_the_failing_operation_is_named(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listNodes')->andThrow(new \RuntimeException('boom'));

        $this->actingAs($this->admin())
            ->getJson('/api/admin/nodes')
            ->assertStatus(500)
            ->assertJsonPath('context', 'index');
    }

    /**
     * Every admin controller that talks to CDNfly must behave the same way —
     * this used to be 64 hand-copied handlers, which is how they drifted.
     */
    /**
     * AdminController kept its own reporter with `catch (\Throwable)` — no
     * variable bound — so the master's reason was discarded and every failure
     * read 「请稍后重试」. Package creation is the one most likely to be
     * rejected for a specific reason (a missing node group, a duplicate name),
     * which is exactly the text an operator needs.
     */
    public function test_package_creation_failures_carry_the_master_reason(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createPackage')
            ->andThrow(new \RuntimeException('CDNfly create package failed: 节点组不存在'));

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', [
                'name' => 'jp-mini',
                'region_id' => 1,
                'node_group_id' => 2,
                'month_price' => '30',
                'quarter_price' => '85',
                'year_price' => '320',
                'groups' => '1',
                'cname_domain' => 1,
            ])
            ->assertStatus(500)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('context', 'createPackage');

        $this->assertStringContainsString(
            '节点组不存在',
            (string) $response->json('message'),
        );
        $this->assertStringContainsString(
            '节点组不存在',
            (string) $response->json('upstream_error'),
        );
    }

    public function test_the_behaviour_is_consistent_across_controllers(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listAllSites')->andThrow(new \RuntimeException('sites down'));
        $cdnfly->shouldReceive('getConfigs')->andThrow(new \RuntimeException('configs down'));
        $cdnfly->shouldReceive('listAllDnsApis')->andThrow(new \RuntimeException('dns down'));
        $cdnfly->shouldReceive('listPackages')->andThrow(new \RuntimeException('packages down'));

        $admin = $this->admin();

        foreach ([
            '/api/admin/sites' => 'sites down',
            '/api/admin/configs' => 'configs down',
            '/api/admin/dns-apis' => 'dns down',
            '/api/admin/packages' => 'packages down',
        ] as $url => $expected) {
            $response = $this->actingAs($admin)->getJson($url)->assertStatus(500);

            $this->assertStringContainsString(
                $expected,
                (string) $response->json('message'),
                "{$url} should carry the upstream message",
            );
        }
    }

    /**
     * An exception with nothing useful in it must not produce a dangling
     * "CDNfly 通讯失败：" with an empty tail.
     */
    public function test_an_empty_upstream_message_degrades_cleanly(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listNodes')->andThrow(new \RuntimeException(''));

        $this->actingAs($this->admin())
            ->getJson('/api/admin/nodes')
            ->assertStatus(500)
            ->assertJsonPath('message', 'CDNfly 通讯失败');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
