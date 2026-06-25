<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataInjection extends Model
{
    protected $fillable = ['label', 'preset', 'injected_ids', 'record_count'];

    protected $casts = [
        'injected_ids' => 'array',
        'record_count' => 'integer',
    ];
}
