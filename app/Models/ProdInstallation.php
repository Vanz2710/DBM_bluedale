<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdInstallation extends Model
{
    protected $table = 'prod_installations';

    protected $fillable = [
        'job_id', 'installation_date', 'quantity',
        'printing_status', 'installation_status', 'installer_pic', 'installation_notes',
    ];

    protected $casts = [
        'installation_date' => 'date:Y-m-d',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProdJob::class, 'job_id');
    }
}
