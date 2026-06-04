<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProdJob extends Model
{
    protected $table = 'prod_jobs';

    protected $fillable = [
        'job_number', 'client_name', 'title', 'product_type', 'location',
        'request_details', 'request_date', 'pic', 'current_stage', 'overall_status',
        'due_date', 'installation_date', 'created_by', 'notes',
    ];

    protected $casts = [
        'request_date'     => 'date',
        'due_date'         => 'date',
        'installation_date'=> 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function application(): HasOne
    {
        return $this->hasOne(ProdApplication::class, 'job_id');
    }

    public function artworkPayment(): HasOne
    {
        return $this->hasOne(ProdArtworkPayment::class, 'job_id');
    }

    public function installation(): HasOne
    {
        return $this->hasOne(ProdInstallation::class, 'job_id');
    }

    public function dismantle(): HasOne
    {
        return $this->hasOne(ProdDismantle::class, 'job_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(ProdComplaint::class, 'job_id')->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProdComment::class, 'job_id')->with('user')->latest();
    }
}
