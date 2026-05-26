<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('forecast_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('forecast_results', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('forecast_products')->nullOnDelete();
            $table->foreignId('forecast_type_id')->nullable()->constrained('forecast_types')->nullOnDelete();
            $table->foreignId('result_id')->nullable()->constrained('forecast_results')->nullOnDelete();
            $table->foreignId('contact_status_id')->nullable()->constrained('contact_statuses')->nullOnDelete();
            $table->foreignId('contact_type_id')->nullable()->constrained('contact_types')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('forecast_date');
            $table->date('forecast_updatedate')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'forecast_date']);
            $table->index(['user_id', 'forecast_date']);
            $table->index('product_id');
            $table->index('forecast_type_id');
            $table->index('result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
        Schema::dropIfExists('forecast_results');
        Schema::dropIfExists('forecast_types');
        Schema::dropIfExists('forecast_products');
    }
};
