<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = config('services.dev_seeder.super_admin_email');
        $password = config('services.dev_seeder.super_admin_password');

        if (empty($email) || empty($password)) {
            throw new \RuntimeException(
                'DEV_SUPER_ADMIN_EMAIL and DEV_SUPER_ADMIN_PASSWORD must be set in .env before running this seeder.'
            );
        }

        $user = User::firstOrNew(['email' => $email]);

        $user->name = 'Super Admin';
        $user->password = $password;
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles(['super-admin']);
    }
}
