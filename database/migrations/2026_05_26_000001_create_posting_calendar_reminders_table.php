<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_calendar_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('platform', 20); // FB, IG, TikTok, LinkedIn, Website
            $table->string('client', 255)->nullable();
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('status', 20)->default('planned'); // planned, design, approval, scheduled, posted
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_calendar_reminders');
    }
};
