<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
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
