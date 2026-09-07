<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_syncs_configured_admin_account(): void
    {
        config([
            'app.maintenance.admin_name' => 'Local Admin',
            'app.maintenance.admin_email' => 'local-admin@example.test',
            'app.maintenance.admin_password' => 'StrongLocalPass123!',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'local-admin@example.test')->firstOrFail();

        $this->assertSame('Local Admin', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('StrongLocalPass123!', $admin->password));
    }

    public function test_admin_user_seeder_promotes_existing_configured_user(): void
    {
        $user = User::factory()->create([
            'email' => 'local-admin@example.test',
            'role' => 'user',
            'password' => 'old-password',
            'email_verified_at' => null,
        ]);

        config([
            'app.maintenance.admin_name' => 'Unified Admin',
            'app.maintenance.admin_email' => 'local-admin@example.test',
            'app.maintenance.admin_password' => 'UnifiedPass123!',
        ]);

        $this->seed(AdminUserSeeder::class);

        $user->refresh();

        $this->assertSame('Unified Admin', $user->name);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('UnifiedPass123!', $user->password));
    }
}
