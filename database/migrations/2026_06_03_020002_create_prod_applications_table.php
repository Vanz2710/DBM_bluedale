<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('prod_jobs')->cascadeOnDelete();
            $table->date('submission_date')->nullable();
            $table->enum('council', ['DBKL', 'MBPJ', 'MBSJ', 'MBSA', 'JKR', 'Others'])->nullable();
            $table->string('council_other')->nullable();
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');
            $table->string('reference_number')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_applications');
    }
};
