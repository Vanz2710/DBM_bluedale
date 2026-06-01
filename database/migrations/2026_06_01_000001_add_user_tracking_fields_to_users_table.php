<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('id');
            $table->boolean('is_approved')->default(false)->after('email');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->unsignedBigInteger('approved_by_id')->nullable()->after('approved_at');
            $table->timestamp('access_requested_at')->nullable()->after('approved_by_id');
            $table->unsignedInteger('login_count')->default(0)->after('access_requested_at');
            $table->timestamp('last_login_at')->nullable()->after('login_count');
            $table->softDeletes();
        });

        // Make email nullable for non-admin users
        DB::statement('ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL');

        // Backfill username for existing users from email local-part
        $users = DB::table('users')->whereNull('username')->get(['id', 'email', 'name']);
        foreach ($users as $user) {
            $base = $user->email
                ? strtolower(explode('@', $user->email)[0])
                : strtolower(preg_replace('/[^a-z0-9_]/', '_', str_replace(' ', '_', $user->name)));

            $base     = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'user';
            $username = $base;
            $suffix   = 1;

            while (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                $username = $base . '_' . $suffix++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        // Auto-approve existing users — they are already in the system
        DB::statement('UPDATE users SET is_approved = 1, approved_at = NOW() WHERE is_approved = 0');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'username', 'is_approved', 'approved_at', 'approved_by_id',
                'access_requested_at', 'login_count', 'last_login_at',
            ]);
        });

        DB::statement('ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL');
    }
};
