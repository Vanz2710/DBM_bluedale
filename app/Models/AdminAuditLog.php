<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 'entity_name',
        'old_values', 'new_values', 'ip_address',
        'revert_data', 'reverted_at', 'reverted_by',
    ];

    // revert_data can hold thousands of record ids for a large merge — never ship it
    // to the list/export views, which only need to know whether a revert is possible.
    protected $hidden = ['revert_data'];
    protected $appends = ['has_revert_data'];

    protected function casts(): array
    {
        return [
            'old_values'  => 'array',
            'new_values'  => 'array',
            'revert_data' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    protected function hasRevertData(): Attribute
    {
        return Attribute::make(
            get: fn () => !empty($this->revert_data['merged_items'] ?? null),
        );
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function reverter()
    {
        return $this->belongsTo(User::class, 'reverted_by')->withTrashed();
    }
}
