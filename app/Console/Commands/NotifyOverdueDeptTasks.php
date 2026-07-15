<?php

namespace App\Console\Commands;

use App\Models\DeptNotification;
use App\Models\DeptTask;
use App\Models\User;
use Illuminate\Console\Command;

class NotifyOverdueDeptTasks extends Command
{
    protected $signature = 'dept:notify-overdue';
    protected $description = 'Notify assignees and admins on newly overdue department tasks';

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
        $created  = 0;

        foreach ($tasks as $task) {
            $assigneeName = $task->assignee?->name ?? 'Unassigned';
            $deptName     = $task->department?->name;

            // Personal nudge straight to whoever the work actually belongs to —
            // previously only admins were told a task was overdue, so the assignee
            // (the one person who can actually act on it) never found out here.
            if ($task->assigned_to) {
                DeptNotification::create([
                    'user_id' => $task->assigned_to,
                    'task_id' => $task->id,
                    'type'    => 'overdue',
                    'message' => "Your task \"{$task->title}\" is overdue.",
                ]);
                $created++;
            }

            $adminMessage = "Task \"{$task->title}\" assigned to {$assigneeName}";
            if ($deptName) {
                $adminMessage .= " ({$deptName})";
            }
            $adminMessage .= ' is overdue.';

            foreach ($adminIds as $adminId) {
                if ($adminId === $task->assigned_to) continue; // already got the personal version above
                DeptNotification::create([
                    'user_id' => $adminId,
                    'task_id' => $task->id,
                    'type'    => 'overdue',
                    'message' => $adminMessage,
                ]);
                $created++;
            }
        }

        $this->info("Overdue check complete. {$created} notification(s) sent for {$tasks->count()} task(s).");
        return Command::SUCCESS;
    }
}
