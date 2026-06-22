<?php

namespace App\Observers;

use App\Models\Deal;
use App\Models\ToDo;

class DealObserver
{
    public function updated(Deal $deal): void
    {
        if ($deal->wasChanged('stage')) {
            ToDo::create([
                'contact_id'        => $deal->contact_id,
                'user_id'           => $deal->user_id,
                'task_id'           => null,
                'todo_date'         => now()->addDays(3)->toDateString(),
                'date_created'      => now()->toDateString(),
                'todo_remark'       => "Auto: Deal \"{$deal->title}\" moved to {$deal->stage}",
                'completion_status' => 'pending',
            ]);
        }
    }
}
