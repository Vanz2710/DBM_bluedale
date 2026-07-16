<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval workflow removed — a task sitting in "waiting_approval" already had its
        // work finished by the assignee, so it becomes completed rather than reverting to in-progress.
        DB::table('dept_tasks')->where('status', 'waiting_approval')->update(['status' => 'completed']);

        DB::statement("ALTER TABLE dept_tasks MODIFY status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('dept_tasks', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::table('dept_tasks', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('due_date');
        });

        DB::statement("ALTER TABLE dept_tasks MODIFY status ENUM('pending','in_progress','waiting_approval','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
