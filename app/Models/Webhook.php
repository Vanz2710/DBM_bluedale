<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = ['name', 'url', 'events', 'secret', 'active', 'format'];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
    ];
}
