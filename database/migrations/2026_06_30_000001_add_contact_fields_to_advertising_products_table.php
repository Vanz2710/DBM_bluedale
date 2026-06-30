<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('site_map_photo');
            $table->string('contact_mobile', 50)->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_mobile']);
        });
    }
};
