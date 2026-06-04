<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contact_incharge_id')->nullable();
            $table->string('email', 255);
            $table->string('name', 255)->nullable();
            $table->timestamps();

            $table->index('email_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_contacts');
    }
};
