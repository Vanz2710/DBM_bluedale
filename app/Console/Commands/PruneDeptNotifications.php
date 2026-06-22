<?php

namespace App\Console\Commands;

use App\Models\DeptNotification;
use Illuminate\Console\Command;

class PruneDeptNotifications extends Command
{
    protected $signature = 'dept:prune-notifications {--days=30 : Delete read notifications older than this many days}';
    protected $description = 'Delete old read dept_notifications to keep the table lean';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $cutoff  = now()->subDays($days);

        $deleted = DeptNotification::whereNotNull('read_at')
            ->where('read_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} read notification(s) older than {$days} days.");
        return Command::SUCCESS;
    }
}
