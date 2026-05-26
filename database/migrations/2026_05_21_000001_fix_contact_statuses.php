<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('contact_statuses')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $statuses = [
            'Agency', 'BP Distribution', 'Client', 'Existing',
            'KLTG - Sabah', 'KLTG Existing', 'KLTG Potential', 'KLTG Raw',
            'KV4L Existing', 'KV4L Potential', 'KV4L Raw',
            'Potential', 'Project', 'Raw', 'Raw New', 'Supplier',
        ];

        foreach ($statuses as $name) {
            DB::table('contact_statuses')->insert(['name' => $name]);
        }
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('contact_statuses')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
