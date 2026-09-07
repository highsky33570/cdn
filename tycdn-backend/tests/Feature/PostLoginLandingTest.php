<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors CDNfly's own split: operators land on the admin console, customers on
 * the user console.
 *
 * /console proxies /v1/user/overview and therefore needs the caller's personal
 * CDNfly key. Operator accounts have no reason to hold one, so landing them there
 * showed "CDNfly API 密钥尚未开通" on every login.
 */
class PostLoginLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lands_on_the_admin_console(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $redirect = $this->loginAndGetRedirect($admin);

        $this->assertStringContainsString('redirect=%2Fconsole%2Fadmin', $redirect);
    }

    public function test_regular_user_lands_on_the_customer_console(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $redirect = $this->loginAndGetRedirect($user);

        $this->assertStringContainsString('redirect=%2Fconsole', $redirect);
        $this->assertStringNotContainsString('%2Fconsole%2Fadmin', $redirect);
    }

    public function test_an_explicit_redirect_still_wins_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $redirect = $this->loginAndGetRedirect($admin, '/console/billing/packages');

        $this->assertStringContainsString('redirect=%2Fconsole%2Fbilling%2Fpackages', $redirect);
    }

    public function test_an_offsite_redirect_is_ignored_in_favour_of_the_role_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $redirect = $this->loginAndGetRedirect($admin, 'https://evil.example.com/steal');

        $this->assertStringNotContainsString('evil.example.com', $redirect);
        $this->assertStringContainsString('redirect=%2Fconsole%2Fadmin', $redirect);
    }

    private function loginAndGetRedirect(User $user, ?string $redirect = null): string
    {
        $payload = [
            'account' => $user->email,
            'password' => 'password',
        ];

        if ($redirect !== null) {
            $payload['redirect'] = $redirect;
        }

        return $this->postJson('/api/auth/login', $payload)
            ->assertOk()
            ->json('data.redirect');
    }
}
