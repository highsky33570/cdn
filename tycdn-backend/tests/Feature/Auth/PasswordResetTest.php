<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertRedirect($this->frontendUrl('/forgot-password'));
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
            'captcha' => 'test-captcha',
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
            'captcha' => 'test-captcha',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertRedirect($this->frontendUrl('/reset-password/'.$notification->token));

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
            'captcha' => 'test-captcha',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_reset_invalidates_existing_sessions_and_rejects_the_old_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'TempMailA1!',
        ]);

        DB::table(config('session.table', 'sessions'))->insert([
            [
                'id' => 'session-a',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => 'stub-session-a',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'session-b',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.2',
                'user_agent' => 'phpunit',
                'payload' => 'stub-session-b',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
            'captcha' => 'test-captcha',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'ResetB2!',
                'password_confirmation' => 'ResetB2!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->assertSame(
            0,
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->count(),
        );

        $user->refresh();

        $this->assertTrue(Hash::check('ResetB2!', $user->password));
        $this->assertFalse(Auth::guard('web')->attempt([
            'email' => $user->email,
            'password' => 'TempMailA1!',
        ]));
        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => $user->email,
            'password' => 'ResetB2!',
        ]));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_link_requires_a_valid_captcha(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->mock(RecaptchaService::class, function ($mock): void {
            $mock->shouldReceive('validate')->andReturn(false);
        });

        $response = $this->postJson(route('password.email'), [
            'email' => $user->email,
            'captcha' => 'WRONG',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('captcha');

        Notification::assertNothingSent();
    }

    public function test_reset_password_link_is_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson(route('password.email'), [
                'email' => $user->email,
                'captcha' => 'test-captcha',
            ]);

            $response->assertOk();
        }

        $response = $this->postJson(route('password.email'), [
            'email' => $user->email,
            'captcha' => 'test-captcha',
        ]);

        $response->assertStatus(429);
    }

}
