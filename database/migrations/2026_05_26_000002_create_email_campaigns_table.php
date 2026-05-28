<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 20)->default('gmail'); // gmail, outlook
            $table->string('sender_email', 255)->nullable();
            $table->string('status', 20)->default('Draft'); // Draft, Scheduled, Sent
            $table->timestamp('schedule_at')->nullable();
            $table->string('subject', 500)->nullable();
            $table->longText('body')->nullable();
            $table->json('audience')->nullable(); // array of {id, name, email}
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('template_id', 50)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
