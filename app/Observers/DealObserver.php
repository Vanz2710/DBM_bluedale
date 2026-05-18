<?php

namespace App\Observers;

use App\Models\Deal;
use App\Models\ToDo;
use App\Services\WebhookDispatcher;

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

            WebhookDispatcher::dispatch('deal.stage_changed', [
                'id'             => $deal->id,
                'title'          => $deal->title,
                'stage'          => $deal->stage,
                'previous_stage' => $deal->getOriginal('stage'),
                'contact_id'     => $deal->contact_id,
                'user_id'        => $deal->user_id,
            ]);
        }

        if ($deal->wasChanged('status')) {
            $event = match ($deal->status) {
                'won'  => 'deal.won',
                'lost' => 'deal.lost',
                default => null,
            };

            if ($event) {
                WebhookDispatcher::dispatch($event, [
                    'id'         => $deal->id,
                    'title'      => $deal->title,
                    'value'      => $deal->value,
                    'contact_id' => $deal->contact_id,
                    'user_id'    => $deal->user_id,
                    'lost_reason' => $deal->lost_reason,
                ]);
            }
        }
    }
}
