<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiTarget extends Model
{
    protected $fillable = ['user_id', 'metric', 'target_value'];

    protected $casts = ['target_value' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
