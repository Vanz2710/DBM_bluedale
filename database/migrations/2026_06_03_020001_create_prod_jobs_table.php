<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 20)->unique();
            $table->string('client_name');
            $table->string('title')->nullable();
            $table->enum('product_type', ['Billboard', 'Bunting', 'Banner', 'Signboard', 'Lamp Post', 'Others'])->default('Billboard');
            $table->string('location')->nullable();
            $table->text('request_details')->nullable();
            $table->date('request_date');
            $table->string('pic')->nullable();
            $table->enum('current_stage', [
                'new_request', 'application', 'artwork_approval',
                'payment_pending', 'printing', 'installation', 'completed', 'cancelled'
            ])->default('new_request');
            $table->enum('overall_status', ['active', 'on_hold', 'completed', 'cancelled'])->default('active');
            $table->date('due_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['current_stage', 'overall_status']);
            $table->index(['due_date', 'current_stage']);
            $table->index('client_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_jobs');
    }
};
