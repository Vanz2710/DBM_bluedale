<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_startdate',
        'project_enddate',
        'project_name',
        'project_remark',
        'contact_id',
        'user_id',
    ];

    protected $casts = [
        'project_startdate' => 'date',
        'project_enddate'   => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
