<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'source_type', 'source_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
