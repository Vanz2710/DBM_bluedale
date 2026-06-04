<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_dismantles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('prod_jobs')->cascadeOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('pic')->nullable();
            $table->enum('status', ['pending', 'scheduled', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_dismantles');
    }
};
