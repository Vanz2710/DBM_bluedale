<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisingProduct extends Model
{
    protected $fillable = [
        'site_name',
        'status',
        'type',
        'product_type',
        'site_code',
        'size',
        'state_city',
        'coordinate',
        'nearest_landmarks',
    ];

    protected $casts = [
        'nearest_landmarks' => 'array',
    ];

    public function bookings()
    {
        return $this->hasMany(AdvertisingProductBooking::class);
    }
}
