<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignContact extends Model
{
    protected $fillable = [
        'email_campaign_id', 'contact_incharge_id', 'email', 'name',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function incharge(): BelongsTo
    {
        return $this->belongsTo(ContactIncharge::class, 'contact_incharge_id');
    }
}
