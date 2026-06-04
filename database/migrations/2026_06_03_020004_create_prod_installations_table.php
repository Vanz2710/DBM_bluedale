<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('prod_jobs')->cascadeOnDelete();
            $table->date('installation_date')->nullable();
            $table->integer('quantity')->default(1);
            $table->enum('printing_status', ['pending', 'in_production', 'ready'])->default('pending');
            $table->enum('installation_status', ['scheduled', 'ongoing', 'completed'])->default('scheduled');
            $table->string('installer_pic')->nullable();
            $table->text('installation_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_installations');
    }
};
