<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
    protected $fillable = ['todo_id', 'followup_date', 'note'];

    protected $casts = ['followup_date' => 'date'];

    public function todo()
    {
        return $this->belongsTo(ToDo::class, 'todo_id');
    }
}
