<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->string('whatsapp_message_id', 255)->nullable()->unique();
            $table->string('message_type', 50)->default('text');
            $table->text('message_text')->nullable();
            $table->string('media_id', 255)->nullable();
            $table->string('status', 50)->default('received');
            $table->timestamp('timestamp')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
