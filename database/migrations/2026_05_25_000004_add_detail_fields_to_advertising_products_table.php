<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->string('site_code')->nullable()->after('product_type');
            $table->string('size')->nullable()->after('site_code');
            $table->string('state_city')->nullable()->after('size');
            $table->string('coordinate')->nullable()->after('state_city');
            $table->json('nearest_landmarks')->nullable()->after('coordinate');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->dropColumn([
                'site_code',
                'size',
                'state_city',
                'coordinate',
                'nearest_landmarks',
            ]);
        });
    }
};
