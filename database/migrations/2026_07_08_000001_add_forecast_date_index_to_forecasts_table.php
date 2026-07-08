<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // forecasts only had composite (contact_id, forecast_date) / (user_id, forecast_date) indexes,
        // so admin-scoped queries that filter/sort by forecast_date alone couldn't use an index.
        $existing = collect(DB::select('SHOW INDEX FROM forecasts'))->pluck('Key_name');
        if (!$existing->contains('forecasts_forecast_date_index')) {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->index('forecast_date', 'forecasts_forecast_date_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropIndexIfExists('forecasts_forecast_date_index');
        });
    }
};
