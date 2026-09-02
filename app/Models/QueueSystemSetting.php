<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'is_open',
    'current_session_started_at',
    'closed_message',
    'closed_at',
    'closed_by',
    'last_reset_at',
    'last_reset_by',
    'day_ended_at',
    'day_ended_by',
])]
class QueueSystemSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'current_session_started_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reset_at' => 'datetime',
            'day_ended_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return once(fn () => static::query()->firstOrCreate([], [
            'is_open' => true,
            'current_session_started_at' => now()->startOfDay(),
        ]));
    }

    public function currentSessionStartedAt(): Carbon
    {
        return $this->current_session_started_at ?? now()->startOfDay();
    }

    public function isDayOpen(): bool
    {
        return $this->day_ended_at === null;
    }

    public function isAcceptingTickets(): bool
    {
        return $this->is_open && $this->isDayOpen();
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function lastResetByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reset_by');
    }

    public function dayEndedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'day_ended_by');
    }
}
