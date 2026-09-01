<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'ticket_number',
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
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
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

    public function scopeToday(Builder $query): Builder
    {
        return $query
            ->where('created_at', '>=', today()->startOfDay())
            ->where('created_at', '<', today()->addDay()->startOfDay());
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
    public static function todayAggregates(): array
    {
        $waitSql = self::durationSecondsSql('created_at', 'called_at');
        $handleSql = self::durationSecondsSql('called_at', 'completed_at');

        $row = self::query()->today()->toBase()
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
     * @return array<string, int>
     */
    public static function todayStatCounts(): array
    {
        $aggregates = self::todayAggregates();

        unset($aggregates['avg_wait_seconds'], $aggregates['avg_handling_seconds']);

        return $aggregates;
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
}
