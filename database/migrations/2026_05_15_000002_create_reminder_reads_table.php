<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20); // 'todo' or 'followup'
            $table->unsignedBigInteger('source_id');
            $table->timestamp('read_at')->useCurrent();
            $table->unique(['user_id', 'source_type', 'source_id'], 'rr_user_type_source_unique');
            $table->index(['user_id', 'source_type'], 'rr_user_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_reads');
    }
};
