<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'product_id',
        'forecast_type_id',
        'result_id',
        'contact_status_id',
        'contact_type_id',
        'amount',
        'forecast_date',
        'forecast_updatedate',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'forecast_date'       => 'date',
        'forecast_updatedate' => 'date',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(ForecastProduct::class, 'product_id');
    }

    public function forecastType()
    {
        return $this->belongsTo(ForecastType::class);
    }

    public function result()
    {
        return $this->belongsTo(ForecastResult::class, 'result_id');
    }

    public function contactStatus()
    {
        return $this->belongsTo(ContactStatus::class, 'contact_status_id');
    }

    public function contactType()
    {
        return $this->belongsTo(ContactType::class, 'contact_type_id');
    }
}
