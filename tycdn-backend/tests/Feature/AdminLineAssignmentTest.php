<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A CDNfly "line" is not an object you create.
 *
 * The console used to offer a 新增线路 form asking for a name, region, CNAME
 * hostname, sort order and failover policy. None of those fields exist on this
 * endpoint — that form was modelled on node groups and could never have
 * succeeded.
 *
 * What POST /v1/lines actually does, verified against the master's own panel
 * source (cdnfly-go/panel/dashboard/js, chunk-49f49657, and the older
 * panel/src/views/node/group/line.html which agrees):
 *
 *   [{node_group_id, node_id, node_ip_id, line_id, line_name, is_backup?}]
 *
 * — an **array** binding node IPs to one DNS line inside one node group. The
 * DNS lines themselves live in a system config, not a CRUD resource.
 */
class AdminLineAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_nodes_are_assigned_to_a_line_as_an_array(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('assignLines')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/lines', [
                'assignments' => [
                    [
                        'node_group_id' => 3,
                        'node_id' => 1,
                        'node_ip_id' => 1,
                        'line_id' => 0,
                        'line_name' => '默认',
                    ],
                    [
                        'node_group_id' => 3,
                        'node_id' => 2,
                        'node_ip_id' => 5,
                        'line_id' => 0,
                        'line_name' => '默认',
                        'is_backup' => 1,
                    ],
                ],
            ])
            ->assertCreated();

        $this->assertCount(2, $received);
        $this->assertSame(3, $received[0]['node_group_id']);
        $this->assertSame('默认', $received[0]['line_name']);
        $this->assertSame(1, $received[1]['is_backup']);
    }

    /**
     * Every field here identifies *which* node goes on *which* line. A missing
     * one would silently bind the wrong thing, so none may be optional.
     */
    public function test_an_incomplete_assignment_is_rejected(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('assignLines');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/lines', [
                'assignments' => [['node_group_id' => 3, 'line_name' => '默认']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'assignments.0.node_id',
                'assignments.0.node_ip_id',
                'assignments.0.line_id',
            ]);
    }

    /**
     * line_id 0 is the default line and must survive a "required" rule that
     * would otherwise treat zero as absent.
     */
    public function test_the_default_line_id_of_zero_is_accepted(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('assignLines')->once()->andReturn(['code' => 0]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/lines', [
                'assignments' => [[
                    'node_group_id' => 1,
                    'node_id' => 1,
                    'node_ip_id' => 1,
                    'line_id' => 0,
                    'line_name' => '默认',
                ]],
            ])
            ->assertCreated();
    }

    /**
     * DNS lines come from a config row whose value is JSON containing a `lines`
     * key that is *itself* a JSON string. Both levels have to be unwrapped.
     */
    public function test_dns_lines_are_unwrapped_from_the_double_encoded_config(): void
    {
        config([
            'services.cdnfly.base_url' => 'https://panel.example.test',
            'services.cdnfly.admin_api_key' => 'k',
            'services.cdnfly.admin_api_secret' => 's',
            'services.cdnfly.outbound_enabled' => true,
        ]);

        $inner = json_encode([
            ['id' => 0, 'name' => '默认'],
            ['id' => 1, 'name' => '电信'],
        ]);

        Http::fake([
            'https://panel.example.test/v1/configs/*' => Http::response([
                'code' => 0,
                'data' => ['value' => json_encode(['lines' => $inner])],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/dns-lines')
            ->assertOk()
            ->assertJsonPath('data.0.name', '默认')
            ->assertJsonPath('data.1.name', '电信');
    }

    public function test_a_non_admin_cannot_assign_lines(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/api/admin/lines', ['assignments' => []])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
