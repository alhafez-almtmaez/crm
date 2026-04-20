<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $adminEmail = env('ADMIN_EMAIL', 'admin@gmail.com');
        $adminName = env('ADMIN_NAME', 'Admin');
        $adminPassword = env('ADMIN_PASSWORD', 'G!gc@vcWzxk*3MMn');

        $adminUser = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => $adminPassword,
            ],
        );

        $adminUser->assignRole($adminRole);
    }
}
