<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'contact_id',
        'channel',
        'direction',
        'whatsapp_message_id',
        'message_type',
        'message_text',
        'media_id',
        'status',
        'timestamp',
        'raw_payload',
    ];

    protected $casts = [
        'timestamp'   => 'datetime',
        'raw_payload' => 'array',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
