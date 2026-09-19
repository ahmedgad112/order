<?php

namespace App\Models;

use App\Enums\StudentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'is_open',
    'enabled_request_types',
    'enabled_student_kinds',
    'current_session_started_at',
    'closed_message',
    'call_template',
    'closed_at',
    'closed_by',
    'last_reset_at',
    'last_reset_by',
    'day_ended_at',
    'day_ended_by',
])]
class QueueSystemSetting extends Model
{
    public const DEFAULT_CALL_TEMPLATE = 'رَقَم {order}، {name}، بُرْجَاء التَّوَجُّه إِلَى {counter}';

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'enabled_request_types' => 'array',
            'enabled_student_kinds' => 'array',
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

    public function callTemplate(): string
    {
        $template = trim((string) ($this->call_template ?? ''));

        return $template === '' ? self::DEFAULT_CALL_TEMPLATE : $template;
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
        $enabled = RequestType::enabledSlugs();

        return $enabled === [] ? RequestType::slugs() : $enabled;
    }

    public function isRequestTypeEnabled(RequestType|string $type): bool
    {
        $value = $type instanceof RequestType ? $type->slug : $type;

        return in_array($value, $this->enabledRequestTypeValues(), true);
    }

    /**
     * @return list<string>
     */
    public function enabledStudentKindValues(): array
    {
        $stored = $this->enabled_student_kinds;

        if (! is_array($stored)) {
            return StudentKind::values();
        }

        return array_values(array_intersect($stored, StudentKind::values()));
    }

    public function isStudentKindEnabled(StudentKind|string $kind): bool
    {
        $value = $kind instanceof StudentKind ? $kind->value : $kind;

        return in_array($value, $this->enabledStudentKindValues(), true);
    }

    /**
     * @return list<string>
     */
    public function enabledQueueLaneValues(): array
    {
        $lanes = [];

        if ($this->isStudentKindEnabled(StudentKind::NewStudent)) {
            $lanes = $this->enabledRequestTypeValues();
        }

        if ($this->isStudentKindEnabled(StudentKind::CurrentStudent)) {
            $lanes[] = RequestType::LANE_CURRENT_STUDENT;
        }

        return $lanes;
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
