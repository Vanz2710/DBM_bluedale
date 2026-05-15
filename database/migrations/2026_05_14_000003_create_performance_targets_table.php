<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->unsignedInteger('weekly_target')->default(0);
            $table->unique(['user_id', 'task_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_targets');
    }
};
