<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'contact_statuses',
            'contact_types',
            'contact_categories',
            'contact_industries',
            'contact_areas',
            'tasks',
        ];

        // Remove duplicate names (keep the lowest id) before adding the constraint
        foreach ($tables as $table) {
            DB::statement("
                DELETE t1 FROM `{$table}` t1
                INNER JOIN `{$table}` t2
                ON t1.name = t2.name AND t1.id > t2.id
            ");
        }

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('name');
            });
        }
    }

    public function down(): void
    {
        foreach (['contact_statuses', 'contact_types', 'contact_categories', 'contact_industries', 'contact_areas', 'tasks'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['name']);
            });
        }
    }
};
