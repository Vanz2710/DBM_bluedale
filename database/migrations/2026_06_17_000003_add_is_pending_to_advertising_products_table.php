<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->boolean('is_pending')->default(false)->after('site_map_photo');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->dropColumn('is_pending');
        });
    }
};
