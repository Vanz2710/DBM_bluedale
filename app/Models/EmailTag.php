<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailTag extends Model
{
    protected $fillable = ['name', 'color'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(EmailContact::class, 'email_contact_tag');
    }
}
