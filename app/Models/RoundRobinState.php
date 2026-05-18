<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundRobinState extends Model
{
    protected $table = 'round_robin_state';

    protected $fillable = ['last_user_id'];
}
