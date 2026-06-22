<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Run this migration ONLY after resolving duplicate contact names.
// Find duplicates with:
//   SELECT name, COUNT(*) c FROM contacts GROUP BY name HAVING c > 1;
// Decide which duplicate to keep, delete the rest, then run:
//   php artisan migrate --path=database/migrations/2026_06_17_000002_add_unique_constraint_to_contacts_name.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unique('name', 'contacts_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_name_unique');
        });
    }
};
