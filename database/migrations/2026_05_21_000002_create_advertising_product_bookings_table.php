<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_product_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertising_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamps();

            $table->unique(['advertising_product_id', 'year', 'month'], 'ad_product_month_unique');
            $table->index(['year', 'month', 'company_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_product_bookings');
    }
};
