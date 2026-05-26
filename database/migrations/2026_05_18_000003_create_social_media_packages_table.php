<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('social_media_packages')->insert([
            ['name' => 'FB IG MANAGEMENT', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'FB ADS SPONSORED', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'TIKTOK MANAGEMENT', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_packages');
    }
};
