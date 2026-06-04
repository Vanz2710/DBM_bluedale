<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prod_artwork_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('prod_jobs')->cascadeOnDelete();
            $table->string('artwork_version')->nullable();
            $table->enum('artwork_status', ['pending', 'in_review', 'revision', 'approved'])->default('pending');
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->string('invoice_number')->nullable();
            $table->date('payment_due_date')->nullable();
            $table->text('artwork_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_artwork_payments');
    }
};
