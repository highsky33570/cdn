<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect($this->frontendLoginUrl('/dashboard'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_non_admin_users_cannot_visit_the_admin_console()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('console.admin'));
        $response->assertForbidden();
    }

    public function test_admin_users_can_visit_the_admin_console()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('console.admin'));
        $response->assertOk();
    }
}
