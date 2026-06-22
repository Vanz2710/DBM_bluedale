<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone email-marketing contact book (synced from CRM PICs, manual, or CSV).
        Schema::create('email_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->enum('status', ['subscribed', 'unsubscribed', 'bounced', 'pending'])->default('subscribed');
            $table->enum('source', ['manual', 'crm', 'import'])->default('manual');
            $table->foreignId('crm_incharge_id')->nullable()->constrained('contact_incharges')->nullOnDelete();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('crm_incharge_id');
        });

        Schema::create('email_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('email_contact_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['email_contact_id', 'email_tag_id']);
        });

        Schema::create('email_audience_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('type', ['static', 'dynamic'])->default('static');
            $table->json('filters')->nullable();        // dynamic-group filter rules
            $table->boolean('is_system')->default(false); // default groups (All Contacts, Leads, ...)
            $table->timestamps();
        });

        Schema::create('email_audience_group_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_audience_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['email_audience_group_id', 'email_contact_id'], 'eag_contact_unique');
        });

        // Per-recipient delivery + tracking state for a campaign send.
        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->enum('status', [
                'pending', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed', 'failed',
            ])->default('pending');
            $table->string('token', 64)->unique();      // opaque token for pixel/click/unsubscribe URLs
            $table->text('error')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['email_campaign_id', 'status']);
        });

        // Raw event stream (sent / open / click / bounce / unsubscribe).
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('event', ['sent', 'delivered', 'open', 'click', 'bounce', 'unsubscribe', 'failed']);
            $table->string('url')->nullable();          // clicked URL
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['email_campaign_id', 'event']);
        });

        // Single-row SMTP + sender + branding configuration.
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();  // encrypted cast in the model
            $table->string('smtp_encryption')->nullable(); // tls | ssl | null
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_address')->nullable();
            $table->text('email_footer')->nullable();
            $table->boolean('tracking_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_settings');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_campaign_recipients');
        Schema::dropIfExists('email_audience_group_contact');
        Schema::dropIfExists('email_audience_groups');
        Schema::dropIfExists('email_contact_tag');
        Schema::dropIfExists('email_tags');
        Schema::dropIfExists('email_contacts');
    }
};
