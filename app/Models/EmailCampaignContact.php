<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaignContact extends Model
{
    protected $fillable = ['email_campaign_id', 'contact_incharge_id', 'email', 'name'];
}
