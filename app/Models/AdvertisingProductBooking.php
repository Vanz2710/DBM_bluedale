<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisingProductBooking extends Model
{
    protected $fillable = [
        'advertising_product_id',
        'booking_group',
        'contact_id',
        'company_name',
        'year',
        'month',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function product()
    {
        return $this->belongsTo(AdvertisingProduct::class, 'advertising_product_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
