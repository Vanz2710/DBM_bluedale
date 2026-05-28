<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaign extends Model
{
    protected $fillable = [
        'user_id', 'name', 'provider', 'sender_email', 'status',
        'schedule_at', 'subject', 'body', 'audience', 'sent_count', 'template_id',
    ];

    protected $casts = [
        'audience'    => 'array',
        'schedule_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
