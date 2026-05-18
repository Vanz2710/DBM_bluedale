<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_emails');
    }
};
