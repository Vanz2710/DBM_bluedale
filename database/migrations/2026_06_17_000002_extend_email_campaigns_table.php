<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->foreignId('audience_group_id')->nullable()->after('user_id')
                ->constrained('email_audience_groups')->nullOnDelete();
            $table->unsignedInteger('delivered_count')->default(0)->after('sent_count');
            $table->unsignedInteger('opened_count')->default(0)->after('delivered_count');
            $table->unsignedInteger('clicked_count')->default(0)->after('opened_count');
            $table->unsignedInteger('bounced_count')->default(0)->after('clicked_count');
            $table->unsignedInteger('unsubscribed_count')->default(0)->after('bounced_count');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audience_group_id');
            $table->dropColumn([
                'delivered_count', 'opened_count', 'clicked_count',
                'bounced_count', 'unsubscribed_count',
            ]);
        });
    }
};
