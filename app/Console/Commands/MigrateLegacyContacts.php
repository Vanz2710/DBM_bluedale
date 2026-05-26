<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Contact;

class MigrateLegacyContacts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:legacy-contacts';

    /**
     * The console command description.
     */
    protected $description = 'Extracts contacts and lookup tables from the legacy database and loads them into the new database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting legacy contacts migration...');

        // Disable foreign key checks to prevent relationship errors during import
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Migrate all lookup tables first
        $this->migrateLookupTable('contact_categories', ContactCategory::class);
        $this->migrateLookupTable('contact_industries', ContactIndustry::class);
        $this->migrateLookupTable('contact_statuses', ContactStatus::class);
        $this->migrateLookupTable('contact_types', ContactType::class);

        // 2. Migrate the main contacts table
        $this->info('Migrating main contacts table...');
        
        $legacyContacts = DB::connection('legacy')->table('contacts')->get();
        
        // Create a visual progress bar in the terminal
        $bar = $this->output->createProgressBar(count($legacyContacts));

        foreach ($legacyContacts as $oldContact) {
            Contact::insert([
                'id' => $oldContact->id, // Preserving exact legacy ID
                'name' => $oldContact->name,
                'address' => $oldContact->address,
                'remark' => $oldContact->remark,
                'user_id' => $oldContact->user_id,
                'status_id' => $oldContact->status_id,
                'type_id' => $oldContact->type_id,
                'category_id' => $oldContact->category_id,
                'industry_id' => $oldContact->industry_id,
                'created_at' => $oldContact->created_at,
                'updated_at' => $oldContact->updated_at,
            ]);
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // 3. Re-enable foreign key checks to lock the database down again
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Contacts migration completed successfully!');
    }

    /**
     * Helper function to migrate simple lookup tables.
     */
    private function migrateLookupTable($tableName, $modelClass)
    {
        $this->info("Migrating {$tableName}...");
        
        $records = DB::connection('legacy')->table($tableName)->get();
        
        foreach ($records as $record) {
            // Using insert() instead of create() to force the preservation of the original ID
            $modelClass::insert([
                'id' => $record->id,
                'name' => $record->name,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);
        }
    }
}
