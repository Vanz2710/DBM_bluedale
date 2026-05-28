<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'site_photo',
        'site_map_photo',
    ];

    protected $casts = [
        'nearest_landmarks' => 'array',
    ];

    protected $appends = ['site_photo_url', 'site_map_photo_url'];

    public function bookings()
    {
        return $this->hasMany(AdvertisingProductBooking::class);
    }

    protected function sitePhotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->site_photo ? Storage::url($this->site_photo) : null);
    }

    protected function siteMapPhotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->site_map_photo ? Storage::url($this->site_map_photo) : null);
    }
}
