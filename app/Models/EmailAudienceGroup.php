<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailAudienceGroup extends Model
{
    protected $fillable = ['name', 'description', 'type', 'filters', 'is_system'];

    protected $casts = [
        'filters'   => 'array',
        'is_system' => 'boolean',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(EmailContact::class, 'email_audience_group_contact');
    }
}
