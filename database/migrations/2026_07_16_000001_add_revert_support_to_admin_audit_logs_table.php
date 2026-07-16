<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->json('revert_data')->nullable()->after('new_values');
            $table->timestamp('reverted_at')->nullable()->after('revert_data');
            $table->foreignId('reverted_by')->nullable()->after('reverted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['reverted_by']);
            $table->dropColumn(['revert_data', 'reverted_at', 'reverted_by']);
        });
    }
};
