<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index('status_id');
            $table->index('industry_id');
            $table->index('type_id');
            $table->index('category_id');
            $table->index('area_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('name');
        });

        Schema::table('to_dos', function (Blueprint $table) {
            $table->index('contact_id');
            $table->index('user_id');
            $table->index('todo_date');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['status_id']);
            $table->dropIndex(['industry_id']);
            $table->dropIndex(['type_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['area_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['name']);
        });

        Schema::table('to_dos', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['todo_date']);
        });
    }
};
