<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            [
                'key'         => 'admin_notification_email',
                'value'       => config('mail.from.address'),
                'label'       => 'Admin Notification Email',
                'description' => 'Email address that receives system alert notifications (inactivity lockouts, new user approval requests).',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
