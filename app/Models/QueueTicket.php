<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Enums\ProcessStep;
use App\Enums\StudentKind;
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
    'student_kind',
    'national_id',
    'request_type',
    'completion_step',
    'college',
    'department',
    'order_number',
    'seat_number',
    'document_kind',
    'document_path',
    'status',
    'deferred_to_id',
    'user_id',
    'called_at',
    'entered_at',
    'paid_at',
    'file_withdrawn_at',
    'documents_reviewed_at',
    'medical_checked_at',
    'face_printed_at',
    'completed_at',
    'file_delivered_at',
])]
#[Hidden(['national_id', 'order_number', 'seat_number', 'public_token', 'document_path'])]
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
            'student_kind' => StudentKind::class,
            'completion_step' => ProcessStep::class,
            'document_kind' => DocumentKind::class,
            'session_started_at' => 'datetime',
            'called_at' => 'datetime',
            'entered_at' => 'datetime',
            'paid_at' => 'datetime',
            'file_withdrawn_at' => 'datetime',
            'documents_reviewed_at' => 'datetime',
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

    public function requestTypeCounter(): ?string
    {
        $counter = RequestType::findBySlug($this->request_type)?->counter_name;

        return filled($counter) ? $counter : null;
    }

    public function resolvedCounterName(): ?string
    {
        return $this->requestTypeCounter() ?? $this->teller?->counter_name;
    }

    public function isCurrentStudent(): bool
    {
        return $this->student_kind === StudentKind::CurrentStudent;
    }

    public function ticketPrefix(): string
    {
        if ($this->isCurrentStudent()) {
            return StudentKind::CurrentStudent->ticketPrefix();
        }

        return RequestType::prefixFor($this->request_type)
            ?? StudentKind::NewStudent->ticketPrefix();
    }

    public function ticketCode(): string
    {
        return $this->ticketPrefix().$this->ticket_number;
    }

    /**
     * @return list<string>
     */
    public static function ticketCodePrefixes(): array
    {
        $prefixes = [
            ...RequestType::prefixes(),
            StudentKind::NewStudent->ticketPrefix(),
            StudentKind::CurrentStudent->ticketPrefix(),
        ];

        usort($prefixes, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return array_values(array_unique($prefixes));
    }

    /**
     * @return array{0: StudentKind, 1: string|null, 2: int}|null
     */
    public static function parseTicketCode(string $search): ?array
    {
        $ticketCode = strtoupper(trim($search));
        $pattern = '/^('.implode('|', array_map(
            fn (string $prefix): string => preg_quote($prefix, '/'),
            self::ticketCodePrefixes(),
        )).')(\d+)$/';

        if (preg_match($pattern, $ticketCode, $matches) !== 1) {
            return null;
        }

        $prefix = $matches[1];
        $number = (int) $matches[2];
        $requestType = RequestType::findByPrefix($prefix);

        if ($requestType instanceof RequestType) {
            return [StudentKind::NewStudent, $requestType->slug, $number];
        }

        $kind = StudentKind::tryFromTicketPrefix($prefix);

        if (! $kind instanceof StudentKind) {
            return null;
        }

        return [$kind, null, $number];
    }

    public function usesAdmissionProcess(): bool
    {
        return ! $this->isCurrentStudent();
    }

    public function queueLaneValue(): ?string
    {
        if ($this->isCurrentStudent()) {
            return RequestType::LANE_CURRENT_STUDENT;
        }

        return $this->request_type;
    }

    /**
     * @param  list<string>  $lanes
     */
    public function scopeForQueueLanes(Builder $query, array $lanes): Builder
    {
        $lanes = array_values(array_intersect($lanes, RequestType::laneValues()));

        if ($lanes === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $laneQuery) use ($lanes): void {
            $requestTypes = array_values(array_filter(
                $lanes,
                fn (string $lane): bool => $lane !== RequestType::LANE_CURRENT_STUDENT,
            ));

            if (in_array(RequestType::LANE_CURRENT_STUDENT, $lanes, true)) {
                $laneQuery->orWhere('student_kind', StudentKind::CurrentStudent);
            }

            if ($requestTypes !== []) {
                $laneQuery->orWhere(function (Builder $newStudentQuery) use ($requestTypes): void {
                    $newStudentQuery
                        ->where(function (Builder $kindQuery): void {
                            $kindQuery
                                ->where('student_kind', StudentKind::NewStudent)
                                ->orWhereNull('student_kind');
                        })
                        ->whereIn('request_type', $requestTypes);
                });
            }
        });
    }

    public function studentKindValue(): string
    {
        return $this->student_kind?->value ?? StudentKind::NewStudent->value;
    }

    public function studentKindLabel(): string
    {
        return $this->student_kind?->label() ?? StudentKind::NewStudent->label();
    }

    public function requestTypeLabel(): ?string
    {
        if ($this->isCurrentStudent()) {
            return $this->studentKindLabel();
        }

        $type = RequestType::findBySlug($this->request_type);

        if (! $type instanceof RequestType) {
            return $this->request_type;
        }

        if ($type->requires_completion_service) {
            $stepLabel = $this->completion_step?->label();

            return $stepLabel
                ? $type->label.' — '.$stepLabel
                : $type->label;
        }

        return $type->label;
    }

    public function completionStepLabel(): ?string
    {
        return $this->completion_step?->label();
    }

    public function collegeLabel(): ?string
    {
        if (blank($this->college)) {
            return null;
        }

        if ($this->isCurrentStudent()) {
            return Faculty::labelFor($this->college) ?? $this->college;
        }

        if (RequestType::findBySlug($this->request_type)?->isCollegeSelectMode()) {
            return College::labelFor($this->college) ?? $this->college;
        }

        return $this->college;
    }

    public function documentKindLabel(): ?string
    {
        return $this->document_kind?->label();
    }

    public function hasDocument(): bool
    {
        return filled($this->document_path);
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
     *     paid: int,
     *     file_withdrawn: int,
     *     documents_reviewed: int,
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
            ->selectRaw('COUNT(CASE WHEN paid_at IS NOT NULL THEN 1 END) as paid')
            ->selectRaw('COUNT(CASE WHEN file_withdrawn_at IS NOT NULL THEN 1 END) as file_withdrawn')
            ->selectRaw('COUNT(CASE WHEN documents_reviewed_at IS NOT NULL THEN 1 END) as documents_reviewed')
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
            'paid' => (int) ($row->paid ?? 0),
            'file_withdrawn' => (int) ($row->file_withdrawn ?? 0),
            'documents_reviewed' => (int) ($row->documents_reviewed ?? 0),
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
     *     paid: int,
     *     file_withdrawn: int,
     *     documents_reviewed: int,
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
        return ProcessStep::values();
    }

    public function scopeForAdmissionProcess(Builder $query): Builder
    {
        return $query->where(function (Builder $kindQuery): void {
            $kindQuery
                ->where('student_kind', StudentKind::NewStudent)
                ->orWhereNull('student_kind');
        });
    }

    public function scopeForCurrentStudentProcess(Builder $query): Builder
    {
        return $query->where('student_kind', StudentKind::CurrentStudent);
    }

    public function scopeForTicketSeries(Builder $query, StudentKind $kind, ?string $requestType = null): Builder
    {
        if ($kind === StudentKind::CurrentStudent) {
            return $query->forCurrentStudentProcess();
        }

        $query->forAdmissionProcess();

        return filled($requestType)
            ? $query->where('request_type', $requestType)
            : $query->whereNull('request_type');
    }

    public function scopeInQueueOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(deferred_to_id, id)')
            ->orderByRaw('deferred_to_id IS NULL DESC')
            ->orderBy('id');
    }

    public function scopeAtProcessStep(Builder $query, string $step): Builder
    {
        return match ($step) {
            'entered' => $query
                ->whereIn('status', TicketStatus::activeValues())
                ->whereNull('entered_at'),
            'paid' => $query
                ->forAdmissionProcess()
                ->whereNotNull('entered_at')
                ->whereNull('paid_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'file_withdrawn' => $query
                ->forAdmissionProcess()
                ->whereNotNull('entered_at')
                ->whereNotNull('paid_at')
                ->whereNull('file_withdrawn_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'documents_reviewed' => $query
                ->forCurrentStudentProcess()
                ->whereNotNull('entered_at')
                ->whereNull('documents_reviewed_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'medical_checked' => $query
                ->forAdmissionProcess()
                ->whereNotNull('entered_at')
                ->whereNotNull('file_withdrawn_at')
                ->whereNull('medical_checked_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'face_printed' => $query
                ->forAdmissionProcess()
                ->whereNotNull('entered_at')
                ->whereNotNull('medical_checked_at')
                ->whereNull('face_printed_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent]),
            'file_delivered' => $query
                ->whereNull('file_delivered_at')
                ->whereNotIn('status', [TicketStatus::Cancelled, TicketStatus::Absent])
                ->where(function (Builder $readyToDeliver): void {
                    $readyToDeliver
                        ->where(function (Builder $admission): void {
                            $admission
                                ->forAdmissionProcess()
                                ->whereNotNull('entered_at')
                                ->whereNotNull('face_printed_at');
                        })
                        ->orWhere(function (Builder $current): void {
                            $current
                                ->forCurrentStudentProcess()
                                ->whereNotNull('documents_reviewed_at');
                        });
                }),
            default => $query,
        };
    }

    public function scopeMatchingSearch(Builder $query, string $search): Builder
    {
        $catalogLabels = College::labelsBySlug() + Faculty::labelsBySlug();
        $matchingCollegeValues = array_keys(array_filter(
            $catalogLabels,
            function (string $label, string $slug) use ($search): bool {
                return str_contains($label, $search)
                    || str_contains($slug, $search);
            },
            ARRAY_FILTER_USE_BOTH,
        ));

        $parsedTicketCode = self::parseTicketCode($search);

        return $query->where(function (Builder $q) use ($search, $matchingCollegeValues, $parsedTicketCode): void {
            $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('national_id', 'like', "%{$search}%")
                ->orWhere('order_number', 'like', "%{$search}%")
                ->orWhere('seat_number', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")
                ->orWhere('ticket_number', 'like', "%{$search}%")
                ->orWhere('college', 'like', "%{$search}%")
                ->when(
                    $parsedTicketCode !== null,
                    function (Builder $codeQuery) use ($parsedTicketCode): void {
                        if ($parsedTicketCode === null) {
                            return;
                        }

                        [$ticketCodeKind, $ticketCodeRequestType, $ticketCodeNumber] = $parsedTicketCode;

                        $codeQuery->orWhere(function (Builder $seriesQuery) use ($ticketCodeKind, $ticketCodeRequestType, $ticketCodeNumber): void {
                            $seriesQuery
                                ->forTicketSeries($ticketCodeKind, $ticketCodeRequestType)
                                ->where('ticket_number', $ticketCodeNumber);
                        });
                    },
                )
                ->when(
                    $matchingCollegeValues !== [],
                    fn (Builder $collegeQuery) => $collegeQuery->orWhereIn('college', $matchingCollegeValues),
                );
        });
    }
}
