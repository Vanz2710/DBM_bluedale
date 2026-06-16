<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => config('services.dev_seeder.super_admin_email')]);

        $user->name = 'Super Admin';
        $user->password = config('services.dev_seeder.super_admin_password');
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles(['super-admin']);
    }
}
