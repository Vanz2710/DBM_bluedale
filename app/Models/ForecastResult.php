<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForecastResult extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function forecasts()
    {
        return $this->hasMany(Forecast::class, 'result_id');
    }
}
