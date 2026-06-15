<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Covers: WHERE user_id=? AND status='won' AND updated_at BETWEEN ? AND ?
        // Used by PerformanceController::team() and ::overview() for deals_won / won_deal_value
        Schema::table('deals', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'updated_at'], 'deals_user_status_updated_at');
        });

        // Covers: WHERE user_id=? AND completion_status='completed' AND completed_at BETWEEN ? AND ?
        // Used by PerformanceController::overview() and ::team() for todos_completed
        Schema::table('to_dos', function (Blueprint $table) {
            $table->index(['user_id', 'completion_status', 'completed_at'], 'todos_user_status_completed_at');
        });

        // Covers follow_ups join queries: WHERE todo_id IN (...) AND completion_status=? AND completed_at BETWEEN ? AND ?
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->index(['todo_id', 'completion_status', 'completed_at'], 'followups_todo_status_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('deals_user_status_updated_at');
        });

        Schema::table('to_dos', function (Blueprint $table) {
            $table->dropIndex('todos_user_status_completed_at');
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex('followups_todo_status_completed_at');
        });
    }
};
