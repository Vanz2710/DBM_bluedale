<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\DeptTask;

class DeptTaskAttachment extends Model
{
    protected $table = 'dept_task_attachments';
    protected $fillable = ['task_id', 'user_id', 'filename', 'path', 'size', 'mime_type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DeptTask::class);
    }
}
