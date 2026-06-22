<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeptTask extends Model
{
    protected $table = 'dept_tasks';

    protected $fillable = [
        'title', 'description', 'department_id', 'assigned_to', 'created_by',
        'priority', 'status', 'due_date', 'requires_approval',
        'is_recurring', 'recurrence_type', 'next_recurrence_date', 'board_position',
    ];

    protected $casts = [
        'due_date'             => 'date',
        'next_recurrence_date' => 'date',
        'requires_approval'    => 'boolean',
        'is_recurring'         => 'boolean',
    ];

    protected $appends = ['is_overdue'];

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) return false;
        if (in_array($this->status, ['completed', 'cancelled'])) return false;
        return $this->due_date->isPast();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DeptTaskComment::class, 'task_id')->with('user')->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DeptTaskAttachment::class, 'task_id')->latest();
    }

    public function scopePending($query)   { return $query->where('status', 'pending'); }
    public function scopeInProgress($query){ return $query->where('status', 'in_progress'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }
    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled'])
                     ->whereNotNull('due_date')
                     ->where('due_date', '<', Carbon::today());
    }
}
