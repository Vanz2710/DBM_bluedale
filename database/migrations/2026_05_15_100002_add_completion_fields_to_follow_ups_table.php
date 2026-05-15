<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('completion_status', 20)->default('pending')->after('note');
            $table->timestamp('completed_at')->nullable()->after('completion_status');
            $table->index('completion_status');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex(['completion_status']);
            $table->dropColumn(['completion_status', 'completed_at']);
        });
    }
};
