<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    protected $fillable = [
        'name', 'subject', 'preview_text', 'body',
        'sender_name', 'sender_email',
        'status', 'scheduled_at', 'sent_at',
        'brevo_campaign_id', 'brevo_list_id',
        'audience_group_id',
        'audience_count', 'sent_count', 'delivered_count',
        'opened_count', 'clicked_count', 'bounced_count', 'unsubscribed_count',
        'open_rate', 'click_rate',
        'user_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'open_rate' => 'float',
        'click_rate' => 'float',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(EmailCampaignContact::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function audienceGroup(): BelongsTo
    {
        return $this->belongsTo(EmailAudienceGroup::class, 'audience_group_id');
    }
}
