<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the outbound webhook and inbound WhatsApp webhook subsystems.
 * Both features were retired; these tables are no longer referenced by any code.
 * The earlier create/alter migrations are left in place for history — this
 * migration runs last and drops the resulting tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('webhooks');
    }

    public function down(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events');
            $table->string('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->string('format', 20)->default('generic');
            $table->timestamps();
        });

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
            $table->index('timestamp');
            $table->index('status');
            $table->index(['contact_id', 'timestamp']);
        });
    }
};
