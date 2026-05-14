<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactIncharge extends Model
{
    protected $fillable = ['contact_id', 'name', 'email', 'phone_mobile', 'phone_office'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
