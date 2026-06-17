<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignRecipient extends Model
{
    protected $fillable = [
        'email_campaign_id', 'email_contact_id', 'email', 'name',
        'status', 'token', 'error',
        'open_count', 'click_count',
        'sent_at', 'opened_at', 'clicked_at',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(EmailContact::class, 'email_contact_id');
    }
}
