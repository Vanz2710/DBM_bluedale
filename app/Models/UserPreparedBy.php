<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreparedBy extends Model
{
    protected $table = 'user_prepared_by';

    protected $fillable = [
        'user_id', 'name', 'title', 'mobile_code', 'mobile_local', 'signature_label',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
