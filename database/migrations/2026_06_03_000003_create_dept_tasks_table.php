<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dept_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'waiting_approval', 'completed', 'cancelled'])->default('pending');
            $table->date('due_date')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly', 'quarterly'])->nullable();
            $table->date('next_recurrence_date')->nullable();
            $table->unsignedInteger('board_position')->default(0);
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dept_tasks');
    }
};
