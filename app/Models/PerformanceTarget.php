<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceTarget extends Model
{
    protected $fillable = ['user_id', 'task_id', 'weekly_target'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
