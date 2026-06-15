<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEditGrant extends Model
{
    protected $fillable = ['user_id', 'target_user_id', 'granted_by'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
