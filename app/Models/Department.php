<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'color', 'icon'];

    public function tasks(): HasMany
    {
        return $this->hasMany(DeptTask::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
