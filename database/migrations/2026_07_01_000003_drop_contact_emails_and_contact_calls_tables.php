<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contact_calls');
    }

    public function down(): void
    {
        Schema::create('contact_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->enum('direction', ['sent', 'received'])->default('sent');
            $table->timestamp('emailed_at');
            $table->timestamps();
            $table->index('emailed_at');
            $table->index(['contact_id', 'emailed_at']);
        });

        Schema::create('contact_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->unsignedSmallInteger('duration')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('called_at');
            $table->timestamps();
            $table->index('called_at');
            $table->index(['contact_id', 'called_at']);
        });
    }
};
