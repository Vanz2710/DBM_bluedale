<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeptNotification extends Model
{
    protected $table = 'dept_notifications';
    protected $fillable = ['user_id', 'task_id', 'type', 'message', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DeptTask::class, 'task_id');
    }
}
