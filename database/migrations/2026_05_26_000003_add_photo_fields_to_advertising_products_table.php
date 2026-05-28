<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->string('site_photo')->nullable()->after('nearest_landmarks');
            $table->string('site_map_photo')->nullable()->after('site_photo');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->dropColumn(['site_photo', 'site_map_photo']);
        });
    }
};
