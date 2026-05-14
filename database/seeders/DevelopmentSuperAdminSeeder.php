<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'superadmin@example.com']);

        $user->name = 'Super Admin';
        $user->password = Hash::make('SuperAdmin@123');
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles(['super-admin']);
    }
}
