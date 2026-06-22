<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->created_by = Auth::id());
        static::updating(fn($m) => $m->updated_by  = Auth::id());

        // reminder_reads uses a polymorphic source_id with no FK — clean up manually
        // only on force-delete; soft-delete leaves children intact
        static::forceDeleting(function (Contact $contact) {
            $todoIds = $contact->todos()->pluck('id');
            if ($todoIds->isNotEmpty()) {
                $followUpIds = FollowUp::whereIn('todo_id', $todoIds)->pluck('id');
                if ($followUpIds->isNotEmpty()) {
                    ReminderRead::where('source_type', 'followup')->whereIn('source_id', $followUpIds)->delete();
                }
                ReminderRead::where('source_type', 'todo')->whereIn('source_id', $todoIds)->delete();
            }
        });
    }

    protected $fillable = [
        'name',
        'address',
        'remark',
        'is_permanently_closed',
        'whatsapp_phone',
        'lead_source',
        'user_id',
        'status_id',
        'type_id',
        'category_id',
        'industry_id',
    ];

    protected $casts = [
        'is_permanently_closed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
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

    public function incharges()
    {
        return $this->hasMany(ContactIncharge::class);
    }

    public function todos()
    {
        return $this->hasMany(ToDo::class)->orderByDesc('todo_date');
    }

    public function emails()
    {
        return $this->hasMany(ContactEmail::class)->orderByDesc('emailed_at');
    }

    public function calls()
    {
        return $this->hasMany(ContactCall::class)->orderByDesc('called_at');
    }

    public function forecasts()
    {
        return $this->hasMany(Forecast::class)->orderByDesc('forecast_date');
    }

    public function latestForecast()
    {
        return $this->hasOne(Forecast::class)->latestOfMany('forecast_date');
    }
}
