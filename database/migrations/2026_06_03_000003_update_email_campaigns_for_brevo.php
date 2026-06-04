<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            // Remove old columns not used in new model
            if (Schema::hasColumn('email_campaigns', 'provider')) {
                $table->dropColumn('provider');
            }
            if (Schema::hasColumn('email_campaigns', 'template_id')) {
                $table->dropColumn('template_id');
            }
            if (Schema::hasColumn('email_campaigns', 'audience')) {
                $table->dropColumn('audience');
            }
            if (Schema::hasColumn('email_campaigns', 'schedule_at')) {
                $table->dropColumn('schedule_at');
            }

            // Add new columns
            if (!Schema::hasColumn('email_campaigns', 'preview_text')) {
                $table->string('preview_text', 255)->nullable()->after('subject');
            }
            if (!Schema::hasColumn('email_campaigns', 'sender_name')) {
                $table->string('sender_name', 255)->nullable()->after('preview_text');
            }
            if (!Schema::hasColumn('email_campaigns', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('email_campaigns', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('email_campaigns', 'audience_count')) {
                $table->unsignedInteger('audience_count')->default(0)->after('sent_at');
            }
            if (!Schema::hasColumn('email_campaigns', 'open_rate')) {
                $table->decimal('open_rate', 5, 2)->nullable()->after('sent_count');
            }
            if (!Schema::hasColumn('email_campaigns', 'click_rate')) {
                $table->decimal('click_rate', 5, 2)->nullable()->after('open_rate');
            }
            if (!Schema::hasColumn('email_campaigns', 'brevo_campaign_id')) {
                $table->unsignedBigInteger('brevo_campaign_id')->nullable()->after('click_rate');
            }
            if (!Schema::hasColumn('email_campaigns', 'brevo_list_id')) {
                $table->unsignedBigInteger('brevo_list_id')->nullable()->after('brevo_campaign_id');
            }

            // Normalise status to lowercase
            $table->string('status', 20)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumnIfExists('preview_text');
            $table->dropColumnIfExists('sender_name');
            $table->dropColumnIfExists('scheduled_at');
            $table->dropColumnIfExists('sent_at');
            $table->dropColumnIfExists('audience_count');
            $table->dropColumnIfExists('open_rate');
            $table->dropColumnIfExists('click_rate');
            $table->dropColumnIfExists('brevo_campaign_id');
            $table->dropColumnIfExists('brevo_list_id');
        });
    }
};
