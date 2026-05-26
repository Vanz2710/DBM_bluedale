<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('package');
            $table->string('month');
            $table->string('content_calendar_status')->default('pending');
            $table->string('artwork_editing_status')->default('pending');
            $table->string('posting_status')->default('pending');
            $table->string('posting_staff_initials', 10)->nullable();
            $table->string('report_status')->default('pending');
            $table->timestamps();

            $table->index(['month', 'company_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_reminders');
    }
};
