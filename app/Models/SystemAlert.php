<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $fillable = ['for_user_id', 'type', 'title', 'body', 'link', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public static function notifyAdmins(string $type, string $title, string $body, ?string $link = null): void
    {
        $adminIds = User::role(['admin', 'super-admin'])->pluck('id');

        foreach ($adminIds as $adminId) {
            static::create([
                'for_user_id' => $adminId,
                'type'        => $type,
                'title'       => $title,
                'body'        => $body,
                'link'        => $link,
            ]);
        }
    }
}
