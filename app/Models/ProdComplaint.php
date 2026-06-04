<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdComplaint extends Model
{
    protected $table = 'prod_complaints';

    protected $fillable = [
        'job_id', 'complaint_date', 'site_location', 'complaint_type',
        'description', 'resolution_status', 'assigned_user_id',
    ];

    protected $casts = [
        'complaint_date' => 'date',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProdJob::class, 'job_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
