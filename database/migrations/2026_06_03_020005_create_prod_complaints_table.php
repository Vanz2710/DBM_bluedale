<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('prod_jobs')->cascadeOnDelete();
            $table->date('complaint_date');
            $table->string('site_location')->nullable();
            $table->enum('complaint_type', [
                'lighting', 'structural', 'missing_panel',
                'printing_defect', 'installation_defect', 'others'
            ]);
            $table->text('description')->nullable();
            $table->enum('resolution_status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_complaints');
    }
};
