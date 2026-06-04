<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdArtworkPayment extends Model
{
    protected $table = 'prod_artwork_payments';

    protected $fillable = [
        'job_id', 'artwork_version', 'artwork_status',
        'payment_amount', 'payment_status', 'invoice_number',
        'payment_due_date', 'artwork_notes',
    ];

    protected $casts = [
        'payment_due_date' => 'date',
        'payment_amount'   => 'decimal:2',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProdJob::class, 'job_id');
    }
}
