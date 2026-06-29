<?php

namespace App\Console\Commands;

use App\Models\ToDo;
use App\Models\User;
use Illuminate\Console\Command;

class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted
        {--days= : Override the retention window (defaults to app.deleted_user_retention_days)}
        {--dry-run : List what would be purged without deleting}';

    protected $description = 'Permanently remove users that have been soft-deleted longer than the retention window';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?: config('app.deleted_user_retention_days', 30));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $candidates = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No soft-deleted users older than {$days} day(s) to purge.");
            return self::SUCCESS;
        }

        $purged  = 0;
        $skipped = 0;

        foreach ($candidates as $user) {
            // SAFETY GUARD: contacts/deals/projects cascade-delete on the users FK, so
            // force-deleting a user who still owns any of them would destroy real CRM
            // data. Those should have been reassigned at delete time; if they weren't
            // (e.g. a legacy deletion before reassignment was mandatory), skip and warn
            // rather than cascade-wipe.
            $ownsWork = $user->contacts()->count()
                + $user->deals()->count()
                + $user->projects()->count()
                + $user->forecasts()->count()
                + ToDo::where('user_id', $user->id)->count();

            if ($ownsWork > 0) {
                $skipped++;
                $this->warn("SKIP #{$user->id} {$user->name} — still owns {$ownsWork} record(s); reassign them before it can be purged.");
                continue;
            }

            if ($dryRun) {
                $this->line("WOULD PURGE #{$user->id} {$user->name} (deleted {$user->deleted_at->toDateString()})");
                $purged++;
                continue;
            }

            // Drop API tokens (Sanctum tokens have no FK, so they would orphan otherwise).
            $user->tokens()->delete();
            $user->forceDelete();
            $purged++;
            $this->line("Purged #{$user->id} {$user->name}");
        }

        $verb = $dryRun ? 'would be purged' : 'purged';
        $this->info("Done — {$purged} user(s) {$verb}, {$skipped} skipped (still own records).");

        return self::SUCCESS;
    }
}
