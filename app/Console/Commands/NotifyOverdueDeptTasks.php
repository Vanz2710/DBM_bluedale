<?php

namespace App\Console\Commands;

use App\Models\DeptNotification;
use App\Models\DeptTask;
use App\Models\User;
use Illuminate\Console\Command;

class NotifyOverdueDeptTasks extends Command
{
    protected $signature = 'dept:notify-overdue';
    protected $description = 'Create in-app overdue notifications for admins on newly overdue department tasks';

    public function handle(): int
    {
        // Use the notification table itself as the dedup store — if an overdue
        // notification already exists for a task, skip it to avoid re-alerting.
        $alreadyNotified = DeptNotification::where('type', 'overdue')
            ->pluck('task_id')
            ->unique();

        $tasks = DeptTask::whereDate('due_date', '<', today())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotIn('id', $alreadyNotified)
            ->with(['assignee:id,name', 'department:id,name'])
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No newly overdue tasks.');
            return Command::SUCCESS;
        }

        $adminIds = User::role(['admin', 'super-admin'])->pluck('id');

        if ($adminIds->isEmpty()) {
            $this->warn('No admin users found — skipping notifications.');
            return Command::SUCCESS;
        }

        $created = 0;

        foreach ($tasks as $task) {
            $assigneeName = $task->assignee?->name ?? 'Unassigned';
            $deptName     = $task->department?->name;
            $message      = "Task \"{$task->title}\" assigned to {$assigneeName}";
            if ($deptName) {
                $message .= " ({$deptName})";
            }
            $message .= ' is overdue.';

            foreach ($adminIds as $adminId) {
                DeptNotification::create([
                    'user_id' => $adminId,
                    'task_id' => $task->id,
                    'type'    => 'overdue',
                    'message' => $message,
                ]);
                $created++;
            }
        }

        $this->info("Overdue check complete. {$created} notification(s) sent for {$tasks->count()} task(s).");
        return Command::SUCCESS;
    }
}
