<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            
            // Text columns matching your exact legacy SQL lengths
            $table->string('name', 500);
            $table->string('address', 255)->nullable();
            $table->string('remark', 800)->nullable();
            
            // Foreign Keys linking to your users and the lookup tables
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('status_id')->nullable()->constrained('contact_statuses')->onDelete('cascade');
            $table->foreignId('type_id')->nullable()->constrained('contact_types')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('contact_categories')->onDelete('cascade');
            $table->foreignId('industry_id')->nullable()->constrained('contact_industries')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
