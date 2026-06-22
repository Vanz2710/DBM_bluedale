<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingFollowUp = collect(DB::select('SHOW INDEX FROM follow_ups'))->pluck('Key_name');
        if (!$existingFollowUp->contains('follow_ups_action_type_index')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->index('action_type', 'follow_ups_action_type_index');
            });
        }

        $existingTodos = collect(DB::select('SHOW INDEX FROM to_dos'))->pluck('Key_name');
        if (!$existingTodos->contains('to_dos_completion_status_index')) {
            Schema::table('to_dos', function (Blueprint $table) {
                $table->index('completion_status', 'to_dos_completion_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndexIfExists('follow_ups_action_type_index');
        });

        Schema::table('to_dos', function (Blueprint $table) {
            $table->dropIndexIfExists('to_dos_completion_status_index');
        });
    }
};
