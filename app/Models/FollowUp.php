<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FollowUp extends Model
{
    protected static function booted(): void
    {
        static::creating(fn($m) => $m->created_by = Auth::id());
        static::updating(fn($m) => $m->updated_by  = Auth::id());

        static::saved(function (FollowUp $followUp) {
            if ($followUp->completion_status === 'completed') {
                $contactId = DB::table('to_dos')->where('id', $followUp->todo_id)->value('contact_id');
                if ($contactId) {
                    DB::table('contacts')
                        ->where('id', $contactId)
                        ->update(['last_contacted_at' => $followUp->completed_at ?? now()]);
                }
            }
        });

        // Handles explicit FollowUp deletes (cascade from ToDo/Contact is covered by their deleting events)
        static::deleting(function (FollowUp $followUp) {
            ReminderRead::where('source_type', 'followup')->where('source_id', $followUp->id)->delete();
        });
    }

    protected $fillable = ['todo_id', 'followup_date', 'action_type', 'note', 'completion_status', 'completed_at'];

    protected $casts = [
        'followup_date' => 'date',
        'completed_at'  => 'datetime',
    ];

    public function todo()
    {
        return $this->belongsTo(ToDo::class, 'todo_id');
    }
}
