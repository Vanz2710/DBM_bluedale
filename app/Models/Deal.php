<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Deal extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->created_by = Auth::id());
        static::updating(fn($m) => $m->updated_by  = Auth::id());
    }

    protected $fillable = [
        'contact_id',
        'user_id',
        'title',
        'stage',
        'value',
        'probability',
        'expected_close_date',
        'status',
        'lost_reason',
        'notes',
    ];

    protected $casts = [
        'expected_close_date' => 'date',
        'value'               => 'decimal:2',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
