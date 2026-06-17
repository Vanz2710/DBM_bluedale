<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdDismantle extends Model
{
    protected $table = 'prod_dismantles';

    protected $fillable = [
        'job_id', 'scheduled_date', 'completion_date', 'pic', 'status', 'notes',
    ];

    protected $casts = [
        'scheduled_date'  => 'date:Y-m-d',
        'completion_date' => 'date:Y-m-d',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProdJob::class, 'job_id');
    }
}
