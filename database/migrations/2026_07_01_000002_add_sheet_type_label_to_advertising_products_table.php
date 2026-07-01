<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->string('sheet_type_label')->nullable()->after('facing');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_products', function (Blueprint $table) {
            $table->dropColumn('sheet_type_label');
        });
    }
};
