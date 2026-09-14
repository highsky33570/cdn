<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating a site on a customer's behalf.
 *
 * The master resolves "current user" from the credential making the call, and
 * ours is the master admin key — so a site created without `uid` is attributed
 * to the admin, and the customer's package is rejected with 「指定的套餐不属于
 * 当前用户」, which names neither the missing field nor the real owner.
 *
 * Payload shape verified against the master's own panel (chunk-0871c1ec,
 * handleAddSite): {uid, user_package, domain, backend[], backend_http_port}.
 */
class AdminSiteCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owning_user_is_sent_with_the_site(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createAdminSite')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['id' => 9];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites', [
                'uid' => 7,
                'user_package' => 1,
                'domain' => 'demo.example.com',
                'backend' => [['addr' => '8.210.246.67']],
                'backend_http_port' => '80',
            ])
            ->assertCreated();

        $this->assertSame(7, $received['uid']);
        $this->assertSame(1, $received['user_package']);
        $this->assertSame('80', $received['backend_http_port']);
    }

    /**
     * Better a named validation error than the master's opaque ownership
     * complaint, which sends you looking at the package rather than the form.
     */
    public function test_a_site_without_an_owner_is_rejected_before_cdnfly(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('createAdminSite');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/sites', [
                'user_package' => 1,
                'domain' => 'demo.example.com',
                'backend' => [['addr' => '8.210.246.67']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uid');
    }

    public function test_a_non_admin_cannot_create_sites(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/api/admin/sites', [
                'uid' => 7,
                'user_package' => 1,
                'domain' => 'demo.example.com',
                'backend' => [['addr' => '1.2.3.4']],
            ])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
