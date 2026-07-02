<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_product_bookings', function (Blueprint $table) {
            $table->string('booking_group', 36)->nullable()->after('advertising_product_id');
            $table->index('booking_group');
        });

        // Backfill: rows created together by one multi-month booking share the same
        // (product, company, start_date, end_date) — group those under one id.
        // Rows with no date range are standalone single-month bookings and each get
        // their own group.
        $groups = [];

        DB::table('advertising_product_bookings')
            ->orderBy('id')
            ->select('id', 'advertising_product_id', 'company_name', 'start_date', 'end_date')
            ->get()
            ->each(function ($row) use (&$groups) {
                if ($row->start_date && $row->end_date) {
                    $key = $row->advertising_product_id . '|' . $row->company_name . '|' . $row->start_date . '|' . $row->end_date;
                    $groupId = $groups[$key] ??= (string) Str::uuid();
                } else {
                    $groupId = (string) Str::uuid();
                }

                DB::table('advertising_product_bookings')->where('id', $row->id)->update(['booking_group' => $groupId]);
            });
    }

    public function down(): void
    {
        Schema::table('advertising_product_bookings', function (Blueprint $table) {
            $table->dropIndex(['booking_group']);
            $table->dropColumn('booking_group');
        });
    }
};
