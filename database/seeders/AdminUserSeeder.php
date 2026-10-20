<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@wecar.mx');
        $password = env('ADMIN_PASSWORD') ?: 'password';
        $name = env('ADMIN_NAME', 'Admin Wecar');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if (! $user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
        }

        Profile::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['full_name' => $name],
        );
    }
}
