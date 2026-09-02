<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\QueueTicketFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable([
    'ticket_number',
    'session_started_at',
    'public_token',
    'full_name',
    'national_id',
    'order_number',
    'status',
    'user_id',
    'called_at',
    'entered_at',
    'medical_checked_at',
    'face_printed_at',
    'completed_at',
    'file_delivered_at',
])]
#[Hidden(['national_id', 'order_number', 'public_token'])]
class QueueTicket extends Model
{
    /** @use HasFactory<QueueTicketFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (QueueTicket $ticket): void {
            if (blank($ticket->public_token)) {
                $ticket->public_token = (string) Str::uuid();
            }

            if (blank($ticket->session_started_at)) {
                $ticket->session_started_at = QueueSystemSetting::current()->currentSessionStartedAt();
            }
        });

        static::saved(fn () => self::forgetPublicStatusCache());
        static::deleted(fn () => self::forgetPublicStatusCache());
    }

    public static function publicStatusCacheKey(): string
    {
        return 'queue.public_status.'.self::currentSessionStartedAt()->timestamp;
    }

    public static function forgetPublicStatusCache(): void
    {
        Cache::forget(self::publicStatusCacheKey());
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'session_started_at' => 'datetime',
            'called_at' => 'datetime',
            'entered_at' => 'datetime',
            'medical_checked_at' => 'datetime',
            'face_printed_at' => 'datetime',
            'completed_at' => 'datetime',
            'file_delivered_at' => 'datetime',
        ];
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function currentSessionStartedAt(): Carbon
    {
        return QueueSystemSetting::current()->currentSessionStartedAt();
    }

    public function scopeOnDate(Builder $query, DateTimeInterface|string $date): Builder
    {
        $day = Carbon::parse($date)->startOfDay();

        return $query
            ->where('created_at', '>=', $day)
            ->where('created_at', '<', $day->copy()->addDay());
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->where('session_started_at', self::currentSessionStartedAt());
    }

    public static function durationSecondsSql(string $fromColumn, string $toColumn): string
    {
        if (self::query()->getConnection()->getDriverName() === 'sqlite') {
            return "(strftime('%s', {$toColumn}) - strftime('%s', {$fromColumn}))";
        }

        return "TIMESTAMPDIFF(SECOND, {$fromColumn}, {$toColumn})";
    }

    /**
     * @return array{
     *     total: int,
     *     waiting: int,
     *     serving: int,
     *     entered: int,
     *     medical_checked: int,
     *     face_printed: int,
     *     file_delivered: int,
     *     completed: int,
     *     cancelled: int,
     *     absent: int,
     *     avg_wait_seconds: int,
     *     avg_handling_seconds: int
     * }
     */
    public static function aggregatesForDate(DateTimeInterface|string $date): array
    {
        $waitSql = self::durationSecondsSql('created_at', 'called_at');
        $handleSql = self::durationSecondsSql('called_at', 'completed_at');

        $row = self::query()->onDate($date)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as waiting', [TicketStatus::Waiting->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as serving', [TicketStatus::Serving->value])
            ->selectRaw('COUNT(CASE WHEN entered_at IS NOT NULL THEN 1 END) as entered')
            ->selectRaw('COUNT(CASE WHEN medical_checked_at IS NOT NULL THEN 1 END) as medical_checked')
            ->selectRaw('COUNT(CASE WHEN face_printed_at IS NOT NULL THEN 1 END) as face_printed')
            ->selectRaw('COUNT(CASE WHEN file_delivered_at IS NOT NULL THEN 1 END) as file_delivered')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as completed', [TicketStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as cancelled', [TicketStatus::Cancelled->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as absent', [TicketStatus::Absent->value])
            ->selectRaw("AVG(CASE WHEN called_at IS NOT NULL THEN {$waitSql} END) as avg_wait")
            ->selectRaw(
                "AVG(CASE WHEN status = ? AND called_at IS NOT NULL AND completed_at IS NOT NULL THEN {$handleSql} END) as avg_handling",
                [TicketStatus::Completed->value],
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'waiting' => (int) ($row->waiting ?? 0),
            'serving' => (int) ($row->serving ?? 0),
            'entered' => (int) ($row->entered ?? 0),
            'medical_checked' => (int) ($row->medical_checked ?? 0),
            'face_printed' => (int) ($row->face_printed ?? 0),
            'file_delivered' => (int) ($row->file_delivered ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'absent' => (int) ($row->absent ?? 0),
            'avg_wait_seconds' => (int) round((float) ($row->avg_wait ?? 0)),
            'avg_handling_seconds' => (int) round((float) ($row->avg_handling ?? 0)),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     waiting: int,
     *     serving: int,
     *     entered: int,
     *     medical_checked: int,
     *     face_printed: int,
     *     file_delivered: int,
     *     completed: int,
     *     cancelled: int,
     *     absent: int,
     *     avg_wait_seconds: int,
     *     avg_handling_seconds: int
     * }
     */
    public static function todayAggregates(): array
    {
        return self::aggregatesForDate(today());
    }

    /**
     * @return array<string, int>
     */
    public static function statCountsForDate(DateTimeInterface|string $date): array
    {
        $aggregates = self::aggregatesForDate($date);

        unset($aggregates['avg_wait_seconds'], $aggregates['avg_handling_seconds']);

        return $aggregates;
    }

    public static function todayStatCounts(): array
    {
        return self::statCountsForDate(today());
    }

    /**
     * @return list<string>
     */
    public static function registrationDates(): array
    {
        return self::query()
            ->toBase()
            ->selectRaw('DATE(created_at) as registration_date')
            ->whereNotNull('created_at')
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('registration_date')
            ->pluck('registration_date')
            ->filter()
            ->map(fn (mixed $date): string => Carbon::parse((string) $date)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TicketStatus::activeValues());
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Waiting);
    }

    public function scopeServing(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Serving);
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Absent);
    }

    /**
     * @return list<string>
     */
    public static function processStepValues(): array
    {
        return ['entered', 'medical_checked', 'face_printed', 'file_delivered'];
    }

    public function scopeAtProcessStep(Builder $query, string $step): Builder
    {
        return match ($step) {
            'entered' => $query
                ->whereIn('status', TicketStatus::activeValues())
                ->whereNull('entered_at'),
            'medical_checked' => $query
                ->whereNotNull('entered_at')
                ->whereNull('medical_checked_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'face_printed' => $query
                ->whereNotNull('medical_checked_at')
                ->whereNull('face_printed_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'file_delivered' => $query
                ->whereNotNull('face_printed_at')
                ->whereNull('file_delivered_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            default => $query,
        };
    }

    public function scopeMatchingSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('national_id', 'like', "%{$search}%")
                ->orWhere('order_number', 'like', "%{$search}%")
                ->orWhere('ticket_number', 'like', "%{$search}%");
        });
    }
}
