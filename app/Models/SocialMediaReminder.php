<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialMediaReminder extends Model
{
    protected $fillable = [
        'contact_id',
        'company_name',
        'package',
        'month',
        'content_calendar_status',
        'artwork_editing_status',
        'posting_status',
        'posting_staff_initials',
        'report_status',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
