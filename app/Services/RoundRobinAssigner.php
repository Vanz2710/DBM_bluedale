<?php

namespace App\Services;

use App\Models\RoundRobinState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoundRobinAssigner
{
    public function nextUserId(): ?int
    {
        return DB::transaction(function () {
            $userIds = User::orderBy('id')->pluck('id');

            if ($userIds->isEmpty()) {
                return null;
            }

            $state  = RoundRobinState::lockForUpdate()->first();
            $lastId = $state?->last_user_id;

            // Pick the first user whose id is strictly greater than lastId; wrap to start if none
            $nextId = $userIds->first(fn($id) => $id > $lastId) ?? $userIds->first();

            if ($state) {
                $state->update(['last_user_id' => $nextId]);
            } else {
                RoundRobinState::create(['last_user_id' => $nextId]);
            }

            return $nextId;
        });
    }
}
