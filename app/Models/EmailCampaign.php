<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    protected $fillable = [
        'user_id', 'name', 'subject', 'preview_text', 'body',
        'sender_name', 'sender_email', 'status',
        'scheduled_at', 'sent_at',
        'audience_count', 'sent_count', 'open_rate', 'click_rate',
        'brevo_campaign_id', 'brevo_list_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmailCampaignContact::class);
    }
}
