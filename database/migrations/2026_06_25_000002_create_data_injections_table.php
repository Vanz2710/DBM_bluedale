<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_injections', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('preset', 50);
            $table->json('injected_ids');
            $table->unsignedInteger('record_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_injections');
    }
};
