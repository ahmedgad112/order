<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'is_open',
    'closed_message',
    'closed_at',
    'closed_by',
    'last_reset_at',
    'last_reset_by',
])]
class QueueSystemSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'closed_at' => 'datetime',
            'last_reset_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return once(fn () => static::query()->firstOrCreate([], [
            'is_open' => true,
        ]));
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function lastResetByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reset_by');
    }
}
