<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('contact_types')->truncate();
        DB::table('contact_categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $types = ['A1', 'A2', 'A3', 'Contract', 'on going', 'reject'];
        foreach ($types as $name) {
            DB::table('contact_types')->insert(['name' => $name]);
        }

        $categories = [
            'Banner ads', 'Billboard', 'Digital advertorial', 'E-catalogue',
            'Enquiry', 'FB Sponsored ads', 'JKR Signage', 'Lamp post bunting',
            'Newspaper', 'Others', 'Project - Outdoor', 'Radio',
            'Social media management', 'TG - CHEMS', 'TG - JHTG', 'TG - KLTG',
            'TG - KNTG', 'TG - KSTG', 'TG - KV4L', 'TG - MKTG', 'TG - SBTG',
            'TG - TBTG', 'TG - TWTG', 'Temp board', 'Travel Guide',
            'Travel guide- Others', 'Website',
        ];
        foreach ($categories as $name) {
            DB::table('contact_categories')->insert(['name' => $name]);
        }
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('contact_types')->truncate();
        DB::table('contact_categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
