<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('urgency', ['normal', 'urgent'])->default('normal')->after('body');
            $table->foreignId('target_user_id')->nullable()->after('urgency')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropColumn(['urgency', 'target_user_id']);
        });
    }
};
