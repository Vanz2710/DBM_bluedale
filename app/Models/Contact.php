<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'remark',
        'user_id',
        'status_id',
        'type_id',
        'category_id',
        'industry_id',
        'area_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(ContactStatus::class);
    }

    public function type()
    {
        return $this->belongsTo(ContactType::class);
    }

    public function category()
    {
        return $this->belongsTo(ContactCategory::class);
    }

    public function industry()
    {
        return $this->belongsTo(ContactIndustry::class);
    }

    public function area()
    {
        return $this->belongsTo(ContactArea::class);
    }

    public function incharges()
    {
        return $this->hasMany(ContactIncharge::class);
    }

    public function todos()
    {
        return $this->hasMany(ToDo::class)->orderByDesc('todo_date');
    }
}
