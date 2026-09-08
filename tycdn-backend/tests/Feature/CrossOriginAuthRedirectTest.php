<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The portal and the console are separate origins (tycdn.org and
 * console.tycdn.org), and Fortify's public auth screens live on the portal.
 *
 * A plain 302 to another origin is fine for a browser navigation but fatal for
 * an Inertia XHR: the browser re-issues the request against the portal, that
 * request carries no CORS headers, and the visit dies as
 *
 *   "Access to XMLHttpRequest at 'https://tycdn.org/verify-email' ... has been
 *    blocked by CORS policy"
 *
 * rather than navigating. Inertia's answer is a 409 carrying X-Inertia-Location,
 * which the client turns into a full page load. These tests pin both halves: the
 * 409 for XHR visits and the ordinary redirect for everything else.
 */
class CrossOriginAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://portal.example.test']);
    }

    /**
     * The reported failure, end to end: /settings/security sits behind
     * `verified`, so an unverified account is bounced to the verification
     * prompt, which bounces to the portal.
     */
    public function test_an_inertia_visit_blocked_by_email_verification_ends_in_an_inertia_location(): void
    {
        $user = User::factory()->unverified()->create();

        // followingRedirects mirrors the browser: XMLHttpRequest follows the
        // same-origin 302 to the verification prompt transparently, so what the
        // Inertia client actually sees is whatever that prompt answers.
        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->followingRedirects()
            ->get('/settings/security')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://portal.example.test/verify-email');
    }

    public function test_a_browser_navigation_still_gets_a_normal_redirect(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify')
            ->assertRedirect('https://portal.example.test/verify-email');
    }

    /**
     * Every portal-hosted auth screen shares the same failure mode, so every one
     * of them has to answer an Inertia visit the same way.
     */
    public function test_guest_auth_screens_answer_inertia_visits_with_a_location(): void
    {
        $screens = [
            '/login' => 'https://portal.example.test/login',
            '/register' => 'https://portal.example.test/register',
            '/forgot-password' => 'https://portal.example.test/forgot-password',
        ];

        foreach ($screens as $path => $expected) {
            $this->withHeaders($this->inertiaHeaders())
                ->get($path)
                ->assertStatus(409)
                ->assertHeader('X-Inertia-Location', $expected);
        }
    }

    /**
     * A wrong X-Inertia-Version makes Inertia answer 409 with a location of its
     * own — pointing back at the requested URL — which would make these tests
     * pass for the wrong reason. Send the version the middleware computes.
     *
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
        ];
    }
}
