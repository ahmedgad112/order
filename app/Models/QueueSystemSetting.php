<?php

namespace App\Models;

use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'is_open',
    'enabled_request_types',
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
            'enabled_request_types' => 'array',
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

    /**
     * @return list<string>
     */
    public function enabledRequestTypeValues(): array
    {
        $stored = $this->enabled_request_types;

        if (! is_array($stored) || $stored === []) {
            return RequestType::values();
        }

        $enabled = array_values(array_intersect($stored, RequestType::values()));

        return $enabled === [] ? RequestType::values() : $enabled;
    }

    public function isRequestTypeEnabled(RequestType|string $type): bool
    {
        $value = $type instanceof RequestType ? $type->value : $type;

        return in_array($value, $this->enabledRequestTypeValues(), true);
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
