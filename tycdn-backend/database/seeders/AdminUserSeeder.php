<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) config('app.maintenance.admin_name', 'admin');
        $email = (string) config('app.maintenance.admin_email', 'admin@tycdn.com');
        $password = (string) config('app.maintenance.admin_password', '');

        if ($email === '' || $password === '') {
            throw new \RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD must be configured before seeding the admin user.');
        }

        User::updateOrCreate([
            'email' => $email,
        ], [
            'name' => $name !== '' ? $name : 'admin',
            'password' => $password,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->command?->info("管理员账号已同步：{$email}");
    }
}
