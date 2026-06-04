<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeptTaskComment extends Model
{
    protected $table = 'dept_task_comments';
    protected $fillable = ['task_id', 'user_id', 'comment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DeptTask::class, 'task_id');
    }
}
