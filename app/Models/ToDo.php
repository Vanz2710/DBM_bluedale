<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToDo extends Model
{
    protected static function booted(): void
    {
        // Handles explicit ToDo deletes (not cascade from Contact, which is covered in Contact::deleting)
        static::deleting(function (ToDo $todo) {
            $followUpIds = $todo->followUps()->pluck('id');
            if ($followUpIds->isNotEmpty()) {
                ReminderRead::where('source_type', 'followup')->whereIn('source_id', $followUpIds)->delete();
            }
            ReminderRead::where('source_type', 'todo')->where('source_id', $todo->id)->delete();
        });
    }

    protected $table = 'to_dos';

    protected $fillable = ['contact_id', 'user_id', 'task_id', 'todo_date', 'date_created', 'todo_remark', 'completion_status', 'completed_at'];

    protected $casts = [
        'todo_date'    => 'date',
        'date_created' => 'date',
        'completed_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class, 'todo_id');
    }
}
