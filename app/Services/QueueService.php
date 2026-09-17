<?php

namespace App\Services;

use App\Enums\ProcessStep;
use App\Enums\TicketStatus;
use App\Events\TicketAbsentEvent;
use App\Events\TicketCalledEvent;
use App\Events\TicketCompletedEvent;
use App\Events\TicketDeletedEvent;
use App\Events\TicketIssuedEvent;
use App\Events\TicketRestoredEvent;
use App\Events\TicketUpdatedEvent;
use App\Http\Resources\PublicTicketResource;
use App\Jobs\GenerateTicketAudioJob;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QueueService
{
    private const int SKIP_POSITIONS = 5;

    public function __construct(private readonly QueueSystemService $systemService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function issueTicket(array $data, ?User $staff = null): QueueTicket
    {
        $ticket = $this->issueTickets($data, $staff, 1)->first();

        if (! $ticket instanceof QueueTicket) {
            throw ValidationException::withMessages([
                'queue' => 'تعذر إصدار الدور.',
            ]);
        }

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, QueueTicket>
     */
    public function issueTickets(array $data, ?User $staff = null, int $count = 1): Collection
    {
        $this->systemService->assertAcceptingTickets();

        [$studentKind, $requestType] = RequestType::resolveLane($data['request_type']);

        if ($staff?->constrainsTicketsToAssignedLanes() && ! $staff->servesQueueLane($data['request_type'])) {
            throw ValidationException::withMessages([
                'request_type' => 'نوع الطلب غير مخصص لحسابك.',
            ]);
        }

        $tickets = DB::transaction(function () use ($data, $studentKind, $requestType, $count): Collection {
            $sessionStartedAt = QueueTicket::currentSessionStartedAt();
            $maxNumber = QueueTicket::query()
                ->today()
                ->forTicketSeries($studentKind, $requestType)
                ->lockForUpdate()
                ->orderByDesc('ticket_number')
                ->value('ticket_number');

            $created = collect();

            for ($offset = 1; $offset <= $count; $offset++) {
                $created->push(QueueTicket::query()->create([
                    'ticket_number' => ($maxNumber ?? 0) + $offset,
                    'session_started_at' => $sessionStartedAt,
                    'full_name' => filled($data['full_name'] ?? null) ? $data['full_name'] : null,
                    'student_kind' => $studentKind,
                    'request_type' => $requestType,
                    'order_number' => filled($data['order_number'] ?? null) ? $data['order_number'] : null,
                    'status' => TicketStatus::Waiting,
                ]));
            }

            return $created;
        });

        $tickets->each(fn (QueueTicket $ticket) => $this->broadcastSafely(new TicketIssuedEvent($ticket)));

        return $tickets;
    }

    public function callNext(User $teller): QueueTicket
    {
        $this->systemService->assertSystemOpen();

        if (! $teller->is_active) {
            throw ValidationException::withMessages([
                'teller' => 'حساب الموظف غير نشط.',
            ]);
        }

        if ($teller->constrainsTicketsToAssignedLanes() && $teller->queueLaneValues() === []) {
            throw ValidationException::withMessages([
                'queue' => 'لم يتم تخصيص أي نوع طلب لحسابك.',
            ]);
        }

        return DB::transaction(function () use ($teller): QueueTicket {
            $query = QueueTicket::query()
                ->today()
                ->waiting()
                ->inQueueOrder()
                ->lockForUpdate();

            $this->constrainToAssignedLanes($query, $teller);

            $nextTicket = $query->first();

            if (! $nextTicket) {
                $hasAnyWaiting = QueueTicket::query()->today()->waiting()->exists();

                throw ValidationException::withMessages([
                    'queue' => $hasAnyWaiting && $teller->constrainsTicketsToAssignedLanes()
                        ? 'لا توجد تذاكر في الانتظار لنوع الطلب المخصص لك.'
                        : 'لا توجد تذاكر في الانتظار.',
                ]);
            }

            $nextTicket->update([
                'status' => TicketStatus::Serving,
                'user_id' => $teller->id,
                'called_at' => now(),
                'deferred_to_id' => null,
            ]);

            $nextTicket->load('teller');

            $this->broadcastSafely(new TicketCalledEvent($nextTicket));
            GenerateTicketAudioJob::dispatch($nextTicket->id)->afterCommit();

            return $nextTicket;
        });
    }

    public function callTicket(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->systemService->assertSystemOpen();
        $this->assertActiveStaff($teller);
        $this->assertTicketMatchesTellerLanes($ticket, $teller);

        return DB::transaction(function () use ($ticket, $teller): QueueTicket {
            $locked = QueueTicket::query()->lockForUpdate()->find($ticket->id);

            if ($locked?->status !== TicketStatus::Waiting) {
                throw ValidationException::withMessages([
                    'ticket' => 'يمكن نداء التذاكر في الانتظار فقط.',
                ]);
            }

            $locked->update([
                'status' => TicketStatus::Serving,
                'user_id' => $teller->id,
                'called_at' => now(),
                'deferred_to_id' => null,
            ]);

            $locked->load('teller');

            $this->broadcastSafely(new TicketCalledEvent($locked));
            GenerateTicketAudioJob::dispatch($locked->id)->afterCommit();

            return $locked->fresh(['teller']);
        });
    }

    public function skipTicket(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketMatchesTellerLanes($ticket, $teller);

        $waitingIds = QueueTicket::query()
            ->today()
            ->waiting()
            ->inQueueOrder()
            ->pluck('id');

        $index = $waitingIds->search($ticket->id);

        if ($index === false) {
            throw ValidationException::withMessages([
                'ticket' => 'يمكن تخطي التذاكر في الانتظار فقط.',
            ]);
        }

        $targetId = $waitingIds->get(min($index + self::SKIP_POSITIONS, $waitingIds->count() - 1));

        if ($targetId === $ticket->id) {
            throw ValidationException::withMessages([
                'ticket' => 'التذكرة بالفعل في نهاية الطابور.',
            ]);
        }

        $ticket->update(['deferred_to_id' => $targetId]);

        $this->broadcastSafely(new TicketUpdatedEvent($ticket->id, $ticket->ticketCode()));

        return $ticket->fresh(['teller']);
    }

    public function completeTicket(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);

        if ($ticket->file_delivered_at === null) {
            throw ValidationException::withMessages([
                'ticket' => 'سجّل تسليم الملف أولاً قبل الإكمال.',
            ]);
        }

        if ($ticket->status === TicketStatus::Completed) {
            throw ValidationException::withMessages([
                'ticket' => 'تم إكمال هذه التذكرة مسبقاً.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Completed,
            'user_id' => $ticket->user_id ?? $teller->id,
            'called_at' => $ticket->called_at ?? now(),
            'completed_at' => now(),
        ]);

        $this->broadcastSafely(new TicketCompletedEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function cancelTicket(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertTicketOwnedByTeller($ticket, $teller);

        if ($ticket->status !== TicketStatus::Serving) {
            throw ValidationException::withMessages([
                'ticket' => 'يمكن إلغاء التذاكر قيد الخدمة فقط.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Cancelled,
            'completed_at' => now(),
        ]);

        $this->broadcastSafely(new TicketCompletedEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function deleteTicket(QueueTicket $ticket, User $admin): void
    {
        abort_unless($admin->canDeleteTickets(), 403, 'ليس لديك صلاحية للوصول.');

        $ticketId = $ticket->id;
        $ticketNumber = $ticket->ticketCode();
        $documentPath = $ticket->document_path;

        $ticket->delete();

        if (filled($documentPath)) {
            Storage::delete($documentPath);
        }

        $this->broadcastSafely(new TicketDeletedEvent($ticketId, $ticketNumber));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTicket(QueueTicket $ticket, array $data, User $admin): QueueTicket
    {
        abort_unless($admin->canEditTickets(), 403, 'ليس لديك صلاحية للوصول.');

        if ($ticket->isCurrentStudent()) {
            $ticket->update([
                'full_name' => $data['full_name'],
                'college' => $data['college'],
                'department' => $data['department'],
                'seat_number' => $data['seat_number'],
            ]);
        } else {
            $requestType = $data['request_type'];

            $ticket->update([
                'full_name' => $data['full_name'],
                'national_id' => $data['national_id'],
                'request_type' => $requestType,
                'completion_step' => RequestType::findBySlug($requestType)?->requires_completion_service
                    ? ($data['completion_step'] ?? null)
                    : null,
                'college' => $data['college'],
                'order_number' => $data['order_number'],
            ]);
        }

        $this->broadcastSafely(new TicketUpdatedEvent($ticket->id, $ticket->ticketCode()));

        return $ticket->fresh(['teller']);
    }

    public function recallTicket(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertTicketOwnedByTeller($ticket, $teller);

        if ($ticket->status !== TicketStatus::Serving) {
            throw ValidationException::withMessages([
                'ticket' => 'يمكن إعادة نداء التذاكر قيد الخدمة فقط.',
            ]);
        }

        $ticket->update([
            'called_at' => now(),
        ]);

        $this->announceTicketCall($ticket);

        return $ticket;
    }

    public function markAbsent(QueueTicket $ticket, User $teller): QueueTicket
    {
        if (! $teller->is_active) {
            throw ValidationException::withMessages([
                'teller' => 'حساب الموظف غير نشط.',
            ]);
        }

        if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Serving], true)) {
            throw ValidationException::withMessages([
                'ticket' => 'يمكن تسجيل "مش موجود" للتذاكر في الانتظار أو قيد الخدمة فقط.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Absent,
            'user_id' => $ticket->user_id ?? $teller->id,
            'called_at' => $ticket->called_at ?? now(),
            'completed_at' => now(),
        ]);

        $ticket->load('teller');

        $this->broadcastSafely(new TicketAbsentEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function restoreTicket(QueueTicket $ticket): QueueTicket
    {
        if ($ticket->status !== TicketStatus::Absent) {
            throw ValidationException::withMessages([
                'ticket' => 'يمكن إرجاع التذاكر المسجلة كـ "مش موجود" فقط.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Waiting,
            'deferred_to_id' => null,
            'user_id' => null,
            'called_at' => null,
            'entered_at' => null,
            'paid_at' => null,
            'file_withdrawn_at' => null,
            'documents_reviewed_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);

        $this->broadcastSafely(new TicketRestoredEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    /**
     * @return Collection<int, QueueTicket>
     */
    public function getAbsentTickets()
    {
        return QueueTicket::query()
            ->today()
            ->absent()
            ->with('teller')
            ->orderByDesc('completed_at')
            ->get();
    }

    public function markEntered(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->systemService->assertSystemOpen();
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);

        if ($ticket->entered_at) {
            throw ValidationException::withMessages([
                'ticket' => 'تم تسجيل طلب الدخول لهذه التذكرة مسبقاً.',
            ]);
        }

        if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Serving], true)) {
            throw ValidationException::withMessages([
                'ticket' => 'لا يمكن تسجيل طلب الدخول لهذه التذكرة.',
            ]);
        }

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'entered_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::Entered);

        return $ticket->fresh(['teller']);
    }

    public function markPaid(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertAdmissionProcess($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->paid_at, 'تم تسجيل الدفع لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل الدفع.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'paid_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::Paid);

        return $ticket->fresh(['teller']);
    }

    public function markFileWithdrawn(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertAdmissionProcess($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->file_withdrawn_at, 'تم تسجيل سحب الملف لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل سحب الملف.');
        $this->assertPreviousCheckpoint($ticket->paid_at, 'سجّل الدفع أولاً قبل سحب الملف.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'file_withdrawn_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::FileWithdrawn);

        return $ticket->fresh(['teller']);
    }

    public function markDocumentsReviewed(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);

        if (! $ticket->isCurrentStudent()) {
            throw ValidationException::withMessages([
                'ticket' => 'مراجعة الورق متاحة لطلبات الطالب الحالي فقط.',
            ]);
        }

        $this->assertCheckpointNotAlreadySet($ticket->documents_reviewed_at, 'تم تسجيل مراجعة الورق لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل مراجعة الورق.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'documents_reviewed_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::DocumentsReviewed);

        return $ticket->fresh(['teller']);
    }

    public function markMedicalChecked(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertAdmissionProcess($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->medical_checked_at, 'تم تسجيل الكشف الطبي لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل الكشف الطبي.');
        $this->assertPreviousCheckpoint($ticket->file_withdrawn_at, 'سجّل سحب الملف أولاً قبل الكشف الطبي.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'medical_checked_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::MedicalChecked);

        return $ticket->fresh(['teller']);
    }

    public function markFacePrinted(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertAdmissionProcess($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->face_printed_at, 'تم تسجيل بصمة الوجه لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل بصمة الوجه.');
        $this->assertPreviousCheckpoint($ticket->medical_checked_at, 'سجّل الكشف الطبي أولاً قبل بصمة الوجه.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'face_printed_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::FacePrinted);

        return $ticket->fresh(['teller']);
    }

    public function markFileDelivered(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->file_delivered_at, 'تم تسليم الملف لهذه التذكرة مسبقاً.');

        if ($ticket->isCurrentStudent()) {
            $this->assertPreviousCheckpoint($ticket->documents_reviewed_at, 'سجّل مراجعة الورق أولاً قبل تسليم الملف.');
        } else {
            $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل تسليم الملف.');
            $this->assertPreviousCheckpoint($ticket->face_printed_at, 'سجّل بصمة الوجه أولاً قبل تسليم الملف.');
        }

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'file_delivered_at' => now(),
        ]);

        $this->announceTicketCall($ticket, ProcessStep::FileDelivered);

        return $ticket->fresh(['teller']);
    }

    /**
     * @return array{
     *     tickets: Collection<int, QueueTicket>,
     *     stats: array<string, int>,
     *     serving: Collection<int, QueueTicket>,
     *     absent: Collection<int, QueueTicket>,
     *     current: QueueTicket|null
     * }
     */
    public function getTellerTickets(User $teller, ?string $status = null, ?string $search = null, ?string $step = null): array
    {
        $query = QueueTicket::query()
            ->today()
            ->with('teller')
            ->inQueueOrder();

        $this->constrainToAssignedLanes($query, $teller);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($step && $step !== 'all') {
            $query->atProcessStep($step);
        }

        if ($search) {
            $query->matchingSearch($search);
        }

        $serving = QueueTicket::query()
            ->today()
            ->serving()
            ->with('teller')
            ->orderBy('called_at');
        $this->constrainToAssignedLanes($serving, $teller);
        $serving = $serving->get();

        $absent = QueueTicket::query()
            ->today()
            ->absent()
            ->with('teller')
            ->latest('updated_at');
        $this->constrainToAssignedLanes($absent, $teller);

        return [
            'tickets' => $query->get(),
            'stats' => QueueTicket::todayStatCounts(),
            'serving' => $serving,
            'absent' => $absent->get(),
            'current' => $serving->firstWhere('user_id', $teller->id),
        ];
    }

    /**
     * @return array{ticket: QueueTicket, people_ahead: int|null, position_in_queue: int|null}
     */
    public function trackTicket(?string $nationalId, ?string $orderNumber, ?string $seatNumber = null): array
    {
        $ticket = QueueTicket::query()
            ->today()
            ->when($nationalId, fn ($query) => $query->where('national_id', $nationalId))
            ->when($orderNumber, fn ($query) => $query->where('order_number', $orderNumber))
            ->when($seatNumber, fn ($query) => $query->where('seat_number', $seatNumber))
            ->with('teller')
            ->latest()
            ->first();

        if (! $ticket) {
            throw ValidationException::withMessages([
                'ticket' => 'لم يتم العثور على تذكرة لهذا اليوم.',
            ]);
        }

        return $this->trackingPayload($ticket);
    }

    /**
     * @return array{ticket: QueueTicket, people_ahead: int|null, position_in_queue: int|null}
     */
    public function scanTicket(QueueTicket $ticket): array
    {
        $ticket->loadMissing('teller');

        return $this->trackingPayload($ticket);
    }

    /**
     * @return array{ticket: QueueTicket, people_ahead: int|null, position_in_queue: int|null}
     */
    private function trackingPayload(QueueTicket $ticket): array
    {
        $peopleAhead = null;
        $positionInQueue = null;

        if ($ticket->status === TicketStatus::Waiting) {
            $waitingIds = QueueTicket::query()
                ->today()
                ->waiting()
                ->inQueueOrder()
                ->pluck('id');

            $position = $waitingIds->search($ticket->id);
            $peopleAhead = $position === false ? null : $position;
            $positionInQueue = $position === false ? null : $position + 1;
        }

        return [
            'ticket' => $ticket,
            'people_ahead' => $peopleAhead,
            'position_in_queue' => $positionInQueue,
        ];
    }

    /**
     * @return array{serving: array<int, array<string, mixed>>, waiting: array<int, array<string, mixed>>, stats: array<string, int>}
     */
    public function getPublicQueueStatus(): array
    {
        return Cache::remember(QueueTicket::publicStatusCacheKey(), 3, function (): array {
            $serving = QueueTicket::query()
                ->today()
                ->serving()
                ->with('teller')
                ->orderBy('called_at')
                ->get();

            $waiting = QueueTicket::query()
                ->today()
                ->waiting()
                ->inQueueOrder()
                ->limit(10)
                ->get();

            $aggregates = QueueTicket::todayAggregates();

            return [
                'serving' => PublicTicketResource::collection($serving)->resolve(),
                'waiting' => PublicTicketResource::collection($waiting)->resolve(),
                'stats' => [
                    'waiting' => $aggregates['waiting'],
                    'serving' => $aggregates['serving'],
                    'completed' => $aggregates['completed'],
                ],
            ];
        });
    }

    private function constrainToAssignedLanes(mixed $query, User $user): void
    {
        if (! $user->constrainsTicketsToAssignedLanes()) {
            return;
        }

        $query->forQueueLanes($user->queueLaneValues());
    }

    private function assertTicketMatchesTellerLanes(QueueTicket $ticket, User $teller): void
    {
        if (! $teller->constrainsTicketsToAssignedLanes()) {
            return;
        }

        $lane = $ticket->queueLaneValue();

        if ($lane === null || ! $teller->servesQueueLane($lane)) {
            throw ValidationException::withMessages([
                'ticket' => 'هذه التذكرة غير مخصصة لنوع الطلب الخاص بك.',
            ]);
        }
    }

    private function assertTicketOwnedByTeller(QueueTicket $ticket, User $teller): void
    {
        if ($ticket->user_id !== $teller->id) {
            throw ValidationException::withMessages([
                'ticket' => 'هذه التذكرة غير مخصصة لك.',
            ]);
        }
    }

    private function assertActiveStaff(User $teller): void
    {
        if (! $teller->is_active) {
            throw ValidationException::withMessages([
                'teller' => 'حساب الموظف غير نشط.',
            ]);
        }
    }

    private function assertTicketIsProcessable(QueueTicket $ticket): void
    {
        if (in_array($ticket->status, [TicketStatus::Cancelled, TicketStatus::Absent], true)) {
            throw ValidationException::withMessages([
                'ticket' => 'لا يمكن تحديث هذه التذكرة.',
            ]);
        }
    }

    private function assertAdmissionProcess(QueueTicket $ticket): void
    {
        if (! $ticket->usesAdmissionProcess()) {
            throw ValidationException::withMessages([
                'ticket' => 'هذه الخطوة غير مطلوبة للطالب الحالي.',
            ]);
        }
    }

    private function assertCheckpointNotAlreadySet(mixed $value, string $message): void
    {
        if ($value) {
            throw ValidationException::withMessages([
                'ticket' => $message,
            ]);
        }
    }

    private function assertPreviousCheckpoint(mixed $value, string $message): void
    {
        if (! $value) {
            throw ValidationException::withMessages([
                'ticket' => $message,
            ]);
        }
    }

    /**
     * @return array{status: TicketStatus, user_id: int, called_at: Carbon}
     */
    private function servingAssignment(QueueTicket $ticket, User $teller): array
    {
        return [
            'status' => TicketStatus::Serving,
            'user_id' => $ticket->user_id ?? $teller->id,
            'called_at' => now(),
        ];
    }

    private function announceTicketCall(QueueTicket $ticket, ?ProcessStep $step = null): void
    {
        $ticket->load('teller');
        $this->broadcastSafely(new TicketCalledEvent($ticket, $step));
        GenerateTicketAudioJob::dispatch($ticket->id, $step?->value);
    }

    private function broadcastSafely(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Queue broadcast failed: '.$exception->getMessage());
        }
    }
}
