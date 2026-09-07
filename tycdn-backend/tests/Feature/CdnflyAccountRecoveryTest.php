<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class CdnflyAccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * If CDNfly was unreachable when the user verified their email, the Verified
     * listener never created the upstream account -- and it only fires once. The
     * user was then blocked from every /api/cdn/* route with no way out, because
     * both recovery paths refused to act without an existing cdnfly_user_id.
     */
    public function test_user_retry_creates_the_cdnfly_account_when_it_was_never_made(): void
    {
        $user = User::factory()->create([
            'cdnfly_user_id' => null,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createCdnflyUser')->once()->andReturn(['cdnfly_user_id' => 4321, 'raw' => []]);
        $cdnfly->shouldReceive('getUserApiKey')->once()->with(4321)->andReturn(null);
        $cdnfly->shouldReceive('enableUserApiKey')->once()->with(4321)
            ->andReturn(['api_key' => 'k-new', 'api_secret' => 's-new']);

        $this->actingAs($user)
            ->postJson('/api/auth/retry-api-key')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $user->refresh();

        $this->assertSame(4321, (int) $user->cdnfly_user_id);
        $this->assertTrue($user->hasCdnflyApiKey());
    }

    public function test_admin_sync_creates_the_cdnfly_account_when_it_was_never_made(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'cdnfly_user_id' => null,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createCdnflyUser')->once()->andReturn(['cdnfly_user_id' => 999, 'raw' => []]);
        $cdnfly->shouldReceive('getUserApiKey')->once()->with(999)->andReturn(null);
        $cdnfly->shouldReceive('enableUserApiKey')->once()->with(999)
            ->andReturn(['api_key' => 'k-admin', 'api_secret' => 's-admin']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/sync-api-key")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue($user->fresh()->hasCdnflyApiKey());
    }

    /**
     * A partially completed earlier run leaves an upstream key already enabled;
     * reusing it avoids desynchronising the copy stored locally.
     */
    public function test_existing_upstream_key_is_reused_rather_than_reissued(): void
    {
        $user = User::factory()->create([
            'cdnfly_user_id' => 77,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('createCdnflyUser');
        $cdnfly->shouldNotReceive('enableUserApiKey');
        $cdnfly->shouldReceive('getUserApiKey')->once()->with(77)
            ->andReturn(['api_key' => 'k-existing', 'api_secret' => 's-existing']);

        $this->actingAs($user)->postJson('/api/auth/retry-api-key')->assertOk();

        $this->assertSame('k-existing', $user->fresh()->cdnfly_api_key);
    }

    public function test_registration_rotates_the_session_id(): void
    {
        Notification::fake();

        $this->startSession();
        $before = session()->getId();

        $this->postJson('/api/auth/register', [
            'name' => 'freshuser',
            'email' => 'fresh@example.com',
            'password' => 'Str0ng-Passw0rd!x',
            'password_confirmation' => 'Str0ng-Passw0rd!x',
        ])->assertCreated();

        $this->assertNotSame($before, session()->getId(), 'the guest session id must not survive login');
    }

    /**
     * storeUser used to validate min:8 while public registration used
     * Password::defaults(), so admins could mint accounts weaker than users could.
     */
    public function test_admin_created_users_obey_the_shared_password_policy(): void
    {
        Password::defaults(fn () => Password::min(12)->symbols());

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Weak Account',
                'email' => 'weak@example.com',
                'password' => 'password1',
                'role' => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
