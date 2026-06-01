<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'username', 'email', 'password', 'phone', 'job_title',
    'settings', 'dashboard_layout',
    'is_approved', 'approved_at', 'approved_by_id', 'access_requested_at',
    'login_count', 'last_login_at', 'inactivity_flagged_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'approved_at'         => 'datetime',
            'access_requested_at' => 'datetime',
            'last_login_at'       => 'datetime',
            'password'            => 'hashed',
            'settings'            => 'array',
            'dashboard_layout'    => 'array',
            'is_approved'         => 'boolean',
            'login_count'            => 'integer',
            'inactivity_flagged_at'  => 'datetime',
        ];
    }

    public function forecasts()
    {
        return $this->hasMany(Forecast::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id')->withTrashed();
    }
}
