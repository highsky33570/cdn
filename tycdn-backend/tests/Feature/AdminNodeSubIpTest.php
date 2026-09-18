<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nodes from a DDoS provider ship with a /29 (5 usable IPs). CDNfly does not
 * auto-detect the OS's secondary IPs — each must be registered as a child node
 * record ({ip, pid}) before it can serve or be used by the IP-switch. These
 * pin the portal endpoints that let an admin do that without the master panel.
 */
class AdminNodeSubIpTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_register_sub_ips(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('addNodeSubIps')
            ->once()
            ->andReturnUsing(function (int $pid, array $ips) use (&$received) {
                $received = [$pid, $ips];

                return ['code' => 0];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/nodes/6/sub-ips', [
                'ips' => ['156.234.124.163', '156.234.124.164'],
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertSame([6, ['156.234.124.163', '156.234.124.164']], $received);
    }

    public function test_invalid_ips_are_rejected_before_cdnfly(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('addNodeSubIps');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/nodes/6/sub-ips', [
                'ips' => ['not-an-ip'],
            ])
            ->assertStatus(422);
    }

    public function test_duplicate_ips_are_rejected(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('addNodeSubIps');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/nodes/6/sub-ips', [
                'ips' => ['156.234.124.163', '156.234.124.163'],
            ])
            ->assertStatus(422);
    }

    /** The node's own IPs are its main record plus any child (pid) records. */
    public function test_node_ips_returns_main_and_sub_ips_only(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listNodes')
            ->once()
            ->andReturn([
                'code' => 0,
                'data' => [
                    ['id' => 6, 'pid' => 0, 'ip' => '156.234.124.162'],
                    ['id' => 20, 'pid' => 6, 'ip' => '156.234.124.163'],
                    ['id' => 21, 'pid' => 6, 'ip' => '156.234.124.164'],
                    // belongs to a different node — must be filtered out
                    ['id' => 7, 'pid' => 0, 'ip' => '156.234.79.50'],
                    ['id' => 30, 'pid' => 7, 'ip' => '156.234.79.51'],
                ],
            ]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/nodes/6/ips')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.ip', '156.234.124.162')
            ->assertJsonPath('data.1.ip', '156.234.124.163')
            ->assertJsonPath('data.2.ip', '156.234.124.164');
    }

    public function test_a_non_admin_cannot_register_sub_ips(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('addNodeSubIps');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/api/admin/nodes/6/sub-ips', [
                'ips' => ['156.234.124.163'],
            ])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
