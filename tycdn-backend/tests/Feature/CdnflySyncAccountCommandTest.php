<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gap this command exists to close.
 *
 * A portal account is linked to CDNfly when it verifies its email, and the
 * self-service retry at POST /api/auth/sync-api-key refuses to run before that
 * verification. An account that was seeded rather than registered — or created
 * while mail delivery was not configured — can therefore never verify, never
 * link, and the console reports CDNfly 未连接 with no way out from inside the
 * app.
 */
class CdnflySyncAccountCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_an_unverified_account_that_the_http_path_refuses_to_touch(): void
    {
        $user = User::factory()->unverified()->create([
            'cdnfly_user_id' => null,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $this->fakeCdnfly();

        $this->artisan('cdnfly:sync-account', ['email' => $user->email])
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(77, (int) $user->cdnfly_user_id);
        $this->assertSame('key-77', $user->cdnfly_api_key);
        $this->assertSame('secret-77', $user->cdnfly_api_secret);
    }

    /**
     * Credentials alone do not unblock the console: /api/cdn/* also sits behind
     * the `verified` middleware, so the command has to say so rather than
     * reporting success and leaving the operator to rediscover it.
     */
    public function test_it_warns_when_the_linked_account_still_cannot_reach_the_api(): void
    {
        $user = User::factory()->unverified()->create([
            'cdnfly_user_id' => null,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $this->fakeCdnfly();

        $this->artisan('cdnfly:sync-account', ['email' => $user->email])
            ->expectsOutputToContain('email is still unverified')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_the_verify_flag_unblocks_an_account_that_cannot_receive_mail(): void
    {
        $user = User::factory()->unverified()->create([
            'cdnfly_user_id' => null,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $this->fakeCdnfly();

        $this->artisan('cdnfly:sync-account', ['email' => $user->email, '--verify' => true])
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(77, (int) $user->cdnfly_user_id);
    }

    /**
     * A half-linked account (upstream user exists, no key stored) must be
     * repaired in place. Creating a second upstream user would orphan the first
     * along with anything already provisioned under it.
     */
    public function test_a_half_linked_account_is_repaired_rather_than_duplicated(): void
    {
        $user = User::factory()->create([
            'cdnfly_user_id' => 55,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldNotReceive('createCdnflyUser');
        $cdnfly->shouldReceive('getUserApiKey')->once()->with(55)->andReturn(null);
        $cdnfly->shouldReceive('enableUserApiKey')->once()->with(55)
            ->andReturn(['api_key' => 'key-55', 'api_secret' => 'secret-55']);

        $this->artisan('cdnfly:sync-account', ['email' => $user->email])
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(55, (int) $user->cdnfly_user_id);
        $this->assertSame('key-55', $user->cdnfly_api_key);
    }

    public function test_an_upstream_rejection_fails_the_command_and_stores_nothing(): void
    {
        $user = User::factory()->create([
            'cdnfly_user_id' => null,
            'cdnfly_api_key' => null,
            'cdnfly_api_secret' => null,
        ]);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createCdnflyUser')
            ->andThrow(new \RuntimeException('CDNfly create user failed: 用户名已存在'));

        $this->artisan('cdnfly:sync-account', ['email' => $user->email])
            ->expectsOutputToContain('用户名已存在')
            ->assertFailed();

        $this->assertNull($user->fresh()->cdnfly_user_id);
    }

    public function test_an_unknown_email_fails_instead_of_creating_an_account(): void
    {
        $this->artisan('cdnfly:sync-account', ['email' => 'nobody@example.test'])
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'nobody@example.test')->count());
    }

    private function fakeCdnfly(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createCdnflyUser')->once()->andReturn(['cdnfly_user_id' => 77, 'raw' => []]);
        $cdnfly->shouldReceive('getUserApiKey')->once()->with(77)->andReturn(null);
        $cdnfly->shouldReceive('enableUserApiKey')->once()->with(77)
            ->andReturn(['api_key' => 'key-77', 'api_secret' => 'secret-77']);
    }
}
