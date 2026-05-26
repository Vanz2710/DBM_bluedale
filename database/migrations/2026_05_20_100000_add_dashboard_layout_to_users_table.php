<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $column = $table->json('dashboard_layout')->nullable();

            if (Schema::hasColumn('users', 'settings')) {
                $column->after('settings');
            } elseif (Schema::hasColumn('users', 'job_title')) {
                $column->after('job_title');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'dashboard_layout')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dashboard_layout');
            });
        }
    }
};
