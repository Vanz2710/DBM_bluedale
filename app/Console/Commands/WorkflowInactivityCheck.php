<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\ToDo;
use Illuminate\Console\Command;

class WorkflowInactivityCheck extends Command
{
    protected $signature = 'workflow:inactivity-check';
    protected $description = 'Create follow-up tasks for contacts with no activity in 30+ days';

    public function handle(): int
    {
        $cutoff = now()->subDays(30)->toDateString();
        $today  = now()->toDateString();

        $contacts = Contact::query()
            ->withMax('todos', 'todo_date')
            ->get()
            ->filter(function (Contact $contact) use ($cutoff) {
                $latest = $contact->todos_max_todo_date;
                if ($latest === null) {
                    return $contact->created_at->lt(now()->subDays(30));
                }
                return $latest < $cutoff;
            });

        $created = 0;

        foreach ($contacts as $contact) {
            $alreadyFlagged = $contact->todos()
                ->where('todo_remark', 'like', 'Auto: No activity%')
                ->where('completion_status', 'pending')
                ->whereDate('created_at', $today)
                ->exists();

            if ($alreadyFlagged) {
                continue;
            }

            ToDo::create([
                'contact_id'        => $contact->id,
                'user_id'           => $contact->user_id,
                'task_id'           => null,
                'todo_date'         => $today,
                'date_created'      => $today,
                'todo_remark'       => 'Auto: No activity for 30+ days — re-engage',
                'completion_status' => 'pending',
            ]);

            $created++;
        }

        $this->info("Inactivity check complete. {$created} reminder(s) created.");

        return Command::SUCCESS;
    }
}
