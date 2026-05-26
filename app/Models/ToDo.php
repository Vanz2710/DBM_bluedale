<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToDo extends Model
{
    protected $table = 'to_dos';

    protected $fillable = ['contact_id', 'user_id', 'task_id', 'todo_date', 'date_created', 'todo_remark'];

    protected $casts = [
        'todo_date'    => 'date',
        'date_created' => 'date',
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
