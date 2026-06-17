<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdApplication extends Model
{
    protected $table = 'prod_applications';

    protected $fillable = [
        'job_id', 'submission_date', 'council', 'council_other',
        'status', 'reference_number', 'remarks',
    ];

    protected $casts = [
        'submission_date' => 'date:Y-m-d',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProdJob::class, 'job_id');
    }
}
