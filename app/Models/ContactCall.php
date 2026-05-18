<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'direction',
        'duration',
        'notes',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
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
