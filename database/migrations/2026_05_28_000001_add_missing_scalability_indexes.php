<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // follow_ups: only completion_status was indexed; date and composite indexes missing
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->index('followup_date');
            $table->index(['todo_id', 'followup_date']);
            $table->index(['followup_date', 'completion_status']);
        });

        // to_dos: individual user_id and todo_date exist; composite + date_created missing
        Schema::table('to_dos', function (Blueprint $table) {
            $table->index('date_created');
            $table->index(['user_id', 'todo_date']);
            $table->index(['user_id', 'date_created']);
        });

        // contacts: individual user_id and created_at exist; composite missing
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        // contact_emails: contact_id/user_id auto-indexed via FK; emailed_at missing
        Schema::table('contact_emails', function (Blueprint $table) {
            $table->index('emailed_at');
            $table->index(['contact_id', 'emailed_at']);
        });

        // contact_calls: contact_id/user_id auto-indexed via FK; called_at missing
        Schema::table('contact_calls', function (Blueprint $table) {
            $table->index('called_at');
            $table->index(['contact_id', 'called_at']);
        });

        // deals: individual user_id and status exist; composite missing
        Schema::table('deals', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'expected_close_date']);
        });

        // whatsapp_messages: contact_id indexed; timestamp and status missing
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index('timestamp');
            $table->index('status');
            $table->index(['contact_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex(['followup_date']);
            $table->dropIndex(['todo_id', 'followup_date']);
            $table->dropIndex(['followup_date', 'completion_status']);
        });

        Schema::table('to_dos', function (Blueprint $table) {
            $table->dropIndex(['date_created']);
            $table->dropIndex(['user_id', 'todo_date']);
            $table->dropIndex(['user_id', 'date_created']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('contact_emails', function (Blueprint $table) {
            $table->dropIndex(['emailed_at']);
            $table->dropIndex(['contact_id', 'emailed_at']);
        });

        Schema::table('contact_calls', function (Blueprint $table) {
            $table->dropIndex(['called_at']);
            $table->dropIndex(['contact_id', 'called_at']);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['user_id', 'expected_close_date']);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['timestamp']);
            $table->dropIndex(['status']);
            $table->dropIndex(['contact_id', 'timestamp']);
        });
    }
};
