<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    protected $fillable = [
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
        'from_name', 'from_email', 'reply_to',
        'company_name', 'company_address', 'email_footer',
        'tracking_enabled',
    ];

    protected $casts = [
        'smtp_password'    => 'encrypted',
        'tracking_enabled' => 'boolean',
    ];

    /**
     * The single configuration row, created on first access.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    public function isConfigured(): bool
    {
        return filled($this->smtp_host) && filled($this->from_email);
    }
}
