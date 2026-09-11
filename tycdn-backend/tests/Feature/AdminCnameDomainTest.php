<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CNAME domains are the zone customer records point at.
 *
 * The master will not generate its DNS line list until one exists, so without
 * this the console can register nodes that are never resolvable. The record is
 * only {id, domain, des} — verified against the master panel (chunk-45f4d7f2,
 * component "cnameDomain").
 */
class AdminCnameDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cname_domain_is_created_with_only_domain_and_des(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createCnameDomain')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['id' => 7];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cname-domains', [
                'domain' => 'cdn.tycdn.org',
                'des' => '主线路',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertSame(['domain' => 'cdn.tycdn.org', 'des' => '主线路'], $received);
    }

    public function test_the_domain_is_required(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/cname-domains', ['des' => 'no domain'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    /**
     * An omitted 备注 must still reach the master as an empty string rather than
     * being dropped, so editing a domain can clear the note.
     */
    public function test_a_missing_description_becomes_an_empty_string(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createCnameDomain')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['id' => 8];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cname-domains', ['domain' => 'cdn.example.com'])
            ->assertCreated();

        $this->assertSame('', $received['des']);
    }

    public function test_a_domain_is_updated(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('updateCnameDomain')
            ->once()
            ->with(7, ['domain' => 'cdn2.tycdn.org', 'des' => ''])
            ->andReturn(['id' => 7]);

        $this->actingAs($this->admin())
            ->putJson('/api/admin/cname-domains/7', ['domain' => 'cdn2.tycdn.org'])
            ->assertOk();
    }

    /** The panel joins a multi-select with commas; accept the same. */
    public function test_several_domains_are_deleted_at_once(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('deleteCnameDomains')
            ->once()
            ->with(['3', '4', '5'])
            ->andReturn([]);

        $this->actingAs($this->admin())
            ->deleteJson('/api/admin/cname-domains/3,4,5')
            ->assertOk();
    }

    public function test_a_delete_without_valid_ids_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->deleteJson('/api/admin/cname-domains/abc')
            ->assertStatus(422);
    }

    public function test_a_non_admin_cannot_manage_cname_domains(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/api/admin/cname-domains', ['domain' => 'x.example.com'])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
