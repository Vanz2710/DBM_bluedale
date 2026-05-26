<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_product_bookings', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('month');
            $table->date('end_date')->nullable()->after('start_date');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('advertising_product_bookings', function (Blueprint $table) {
            $table->dropIndex(['start_date', 'end_date']);
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
