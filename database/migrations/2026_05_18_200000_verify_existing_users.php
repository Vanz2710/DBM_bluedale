<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Mark all pre-existing users as verified so they aren't locked out
        // when email verification is introduced. New users will be unverified
        // by default and must verify before accessing the CRM.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void {}
};
