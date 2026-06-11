<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOldCrm extends Command
{
    protected $signature = 'migrate:old-crm
                            {--force : Skip confirmation prompt}
                            {--skip-contacts : Skip truncating and re-importing contacts (use if contacts are already correct)}';

    protected $description = 'Full migration from bluedale_crmbgoc into the new CRM. Wipes and re-imports contacts, lookup tables, contact_incharges, to_dos, and follow_ups.';

    private const CHUNK = 500;

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $this->warn('=== OLD CRM MIGRATION ===');
        $this->warn('This will WIPE and re-import: contacts, lookup tables, contact_incharges, to_dos, follow_ups.');

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to proceed?')) {
            $this->info('Aborted.');
            return 0;
        }

        try {
            DB::connection('old_crm')->getPdo();
            $this->info('Connected to old_crm database.');
        } catch (\Exception $e) {
            $this->error('Cannot connect to old_crm database: ' . $e->getMessage());
            $this->error('Make sure bluedale_crmbgoc is imported into MySQL on port 3307 and DB_OLD_CRM_* vars are set.');
            return 1;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            if (! $this->option('skip-contacts')) {
                $this->importLookupTables();
                $this->importTasks();
                $this->importContacts();
            }

            $this->importContactIncharges();
            $this->importToDos();
            $this->importFollowUps();

        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('=== Migration complete ===');
        $this->table(['Table', 'Rows imported'], $this->summary);

        return 0;
    }

    // ──────────────────────────────────────────────────────────────
    private array $summary = [];

    private function track(string $table, int $count): void
    {
        $this->summary[] = [$table, number_format($count)];
    }

    // ──────────────────────────────────────────────────────────────
    // Lookup tables
    // ──────────────────────────────────────────────────────────────
    private function importLookupTables(): void
    {
        $lookups = ['contact_statuses', 'contact_types', 'contact_categories', 'contact_industries'];

        foreach ($lookups as $table) {
            $this->info("Importing {$table}...");
            DB::table($table)->truncate();

            $rows = DB::connection('old_crm')->table($table)->get();
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = ['id' => $row->id, 'name' => $row->name, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at];
            }
            if ($batch) {
                DB::table($table)->insert($batch);
            }
            $this->track($table, count($batch));
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Tasks
    // ──────────────────────────────────────────────────────────────
    private function importTasks(): void
    {
        $this->info('Importing tasks...');
        DB::table('tasks')->truncate();

        $rows = DB::connection('old_crm')->table('tasks')->get();
        $batch = [];
        foreach ($rows as $row) {
            $batch[] = ['id' => $row->id, 'name' => $row->name, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at];
        }
        if ($batch) {
            DB::table('tasks')->insert($batch);
        }
        $this->track('tasks', count($batch));
    }

    // ──────────────────────────────────────────────────────────────
    // Contacts
    // ──────────────────────────────────────────────────────────────
    private function importContacts(): void
    {
        $userMap = $this->buildUserMap();

        $this->info('Clearing dependent tables...');
        DB::table('follow_ups')->truncate();
        DB::table('to_dos')->truncate();
        DB::table('contact_incharges')->truncate();
        DB::table('contacts')->truncate();

        $total = DB::connection('old_crm')->table('contacts')->count();
        $this->info("Importing {$total} contacts...");
        $bar = $this->output->createProgressBar($total);
        $imported = 0;

        DB::connection('old_crm')->table('contacts')->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($userMap, $bar, &$imported) {
            $batch = [];
            foreach ($rows as $c) {
                $batch[] = [
                    'id'          => $c->id,
                    'name'        => $c->name,
                    'address'     => $c->address,
                    'remark'      => $c->remark,
                    'user_id'     => $userMap[$c->user_id] ?? null,
                    'status_id'   => $c->status_id,
                    'type_id'     => $c->type_id,
                    'category_id' => $c->category_id,
                    'industry_id' => $c->industry_id,
                    'created_at'  => $c->created_at,
                    'updated_at'  => $c->updated_at,
                ];
            }
            DB::table('contacts')->insert($batch);
            $imported += count($batch);
            $bar->advance(count($batch));
        });

        $bar->finish();
        $this->newLine();
        $this->track('contacts', $imported);
    }

    // ──────────────────────────────────────────────────────────────
    // Contact incharges
    // ──────────────────────────────────────────────────────────────
    private function importContactIncharges(): void
    {
        DB::table('contact_incharges')->truncate();

        $validContactIds = DB::table('contacts')->pluck('id')->flip()->all();

        $total = DB::connection('old_crm')->table('contact_incharges')->count();
        $this->info("Importing {$total} contact_incharges...");
        $bar = $this->output->createProgressBar($total);
        $imported = 0;
        $skipped  = 0;

        DB::connection('old_crm')->table('contact_incharges')->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($validContactIds, $bar, &$imported, &$skipped) {
            $batch = [];
            foreach ($rows as $r) {
                if (! isset($validContactIds[$r->contact_id])) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                $batch[] = [
                    'id'           => $r->id,
                    'contact_id'   => $r->contact_id,
                    'name'         => mb_substr($r->name ?? '', 0, 255) ?: null,
                    'email'        => mb_substr($r->email ?? '', 0, 255) ?: null,
                    'phone_mobile' => mb_substr($r->phone_mobile ?? '', 0, 50) ?: null,
                    'phone_office' => mb_substr($r->phone_office ?? '', 0, 50) ?: null,
                    'created_at'   => $r->created_at,
                    'updated_at'   => $r->updated_at,
                ];
            }
            if ($batch) {
                DB::table('contact_incharges')->insert($batch);
                $imported += count($batch);
                $bar->advance(count($batch));
            }
        });

        $bar->finish();
        $this->newLine();
        if ($skipped) {
            $this->warn("  {$skipped} contact_incharges skipped (orphaned contact_id).");
        }
        $this->track('contact_incharges', $imported);
    }

    // ──────────────────────────────────────────────────────────────
    // To-dos
    // ──────────────────────────────────────────────────────────────
    private function importToDos(): void
    {
        DB::table('follow_ups')->truncate();
        DB::table('to_dos')->truncate();

        $userMap         = $this->buildUserMap();
        $validContactIds = DB::table('contacts')->pluck('id')->flip()->all();
        $validTaskIds    = DB::table('tasks')->pluck('id')->flip()->all();
        $today           = now()->toDateString();

        $total = DB::connection('old_crm')->table('to_dos')->count();
        $this->info("Importing {$total} to_dos...");
        $bar = $this->output->createProgressBar($total);
        $imported = 0;
        $skipped  = 0;

        DB::connection('old_crm')->table('to_dos')->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($userMap, $validContactIds, $validTaskIds, $today, $bar, &$imported, &$skipped) {
            $batch = [];
            foreach ($rows as $r) {
                if (! isset($validContactIds[$r->contact_id])) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                $isCompleted = $r->todo_date < $today;
                $batch[] = [
                    'id'                => $r->id,
                    'contact_id'        => $r->contact_id,
                    'user_id'           => $userMap[$r->user_id] ?? null,
                    'task_id'           => isset($validTaskIds[$r->task_id]) ? $r->task_id : null,
                    'todo_date'         => $r->todo_date,
                    'date_created'      => $r->created_at ? substr($r->created_at, 0, 10) : $r->todo_date,
                    'todo_remark'       => $r->todo_remark,
                    'completion_status' => $isCompleted ? 'completed' : 'pending',
                    'completed_at'      => $isCompleted ? ($r->updated_at ?? $r->todo_date) : null,
                    'created_at'        => $r->created_at,
                    'updated_at'        => $r->updated_at,
                ];
            }
            if ($batch) {
                DB::table('to_dos')->insert($batch);
                $imported += count($batch);
                $bar->advance(count($batch));
            }
        });

        $bar->finish();
        $this->newLine();
        if ($skipped) {
            $this->warn("  {$skipped} to_dos skipped (orphaned contact_id).");
        }
        $this->track('to_dos', $imported);
    }

    // ──────────────────────────────────────────────────────────────
    // Follow-ups
    // ──────────────────────────────────────────────────────────────
    private function importFollowUps(): void
    {
        DB::table('follow_ups')->truncate();

        $validTodoIds = DB::table('to_dos')->pluck('id')->flip()->all();
        $today = now()->toDateString();

        $total = DB::connection('old_crm')->table('follow_ups')->count();
        $this->info("Importing {$total} follow_ups...");
        $bar = $this->output->createProgressBar($total);
        $imported = 0;
        $skipped  = 0;

        DB::connection('old_crm')->table('follow_ups')->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($validTodoIds, $today, $bar, &$imported, &$skipped) {
            $batch = [];
            foreach ($rows as $r) {
                if (! isset($validTodoIds[$r->todo_id])) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                $isCompleted = $r->followup_date < $today;
                $batch[] = [
                    'id'                => $r->id,
                    'todo_id'           => $r->todo_id,
                    'followup_date'     => $r->followup_date,
                    'note'              => $r->followup_remark,
                    'action_type'       => null,
                    'completion_status' => $isCompleted ? 'completed' : 'pending',
                    'completed_at'      => $isCompleted ? ($r->updated_at ?? $r->followup_date) : null,
                    'created_at'        => $r->created_at,
                    'updated_at'        => $r->updated_at,
                ];
            }
            if ($batch) {
                DB::table('follow_ups')->insert($batch);
                $imported += count($batch);
                $bar->advance(count($batch));
            }
        });

        $bar->finish();
        $this->newLine();
        if ($skipped) {
            $this->warn("  {$skipped} follow_ups skipped (orphaned todo_id).");
        }
        $this->track('follow_ups', $imported);
    }

    // ──────────────────────────────────────────────────────────────
    // User ID mapper
    // ──────────────────────────────────────────────────────────────
    private ?array $userMapCache = null;

    private function buildUserMap(): array
    {
        if ($this->userMapCache !== null) {
            return $this->userMapCache;
        }

        $oldUsers = DB::connection('old_crm')->table('users')->get();
        $newUsers = DB::table('users')->get();

        $byEmail = [];
        $byName  = [];
        foreach ($newUsers as $u) {
            $byEmail[strtolower($u->email)] = $u->id;
            $byName[strtolower($u->name)]   = $u->id;
        }

        $map        = [];
        $fallbackId = $newUsers->first()?->id;
        $unmapped   = [];

        foreach ($oldUsers as $old) {
            $email = strtolower($old->email);
            $name  = strtolower($old->name);

            if (isset($byEmail[$email])) {
                $map[$old->id] = $byEmail[$email];
            } elseif (isset($byName[$name])) {
                $map[$old->id] = $byName[$name];
            } else {
                $map[$old->id] = $fallbackId;
                $unmapped[]    = "  old id={$old->id} ({$old->name} / {$old->email}) → fallback id={$fallbackId}";
            }
        }

        if ($unmapped) {
            $this->warn('Some old users could not be matched — assigned to fallback user:');
            foreach ($unmapped as $line) {
                $this->line($line);
            }
        }

        $this->userMapCache = $map;
        return $map;
    }
}
