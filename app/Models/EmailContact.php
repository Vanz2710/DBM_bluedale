<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailContact extends Model
{
    protected $fillable = [
        'full_name', 'email', 'phone', 'company',
        'status', 'source', 'crm_incharge_id', 'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(EmailTag::class, 'email_contact_tag');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(EmailAudienceGroup::class, 'email_audience_group_contact');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function scopeSubscribed($query)
    {
        return $query->where('status', 'subscribed');
    }
}
