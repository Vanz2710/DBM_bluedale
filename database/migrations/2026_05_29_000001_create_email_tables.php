<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject')->default('');
            $table->string('preview_text')->nullable();
            $table->text('body')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('brevo_campaign_id')->nullable();
            $table->unsignedBigInteger('brevo_list_id')->nullable();
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->decimal('open_rate', 5, 2)->nullable();
            $table->decimal('click_rate', 5, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_incharge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_contacts');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_templates');
    }
};
