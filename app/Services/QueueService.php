<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketAbsentEvent;
use App\Events\TicketCalledEvent;
use App\Events\TicketCompletedEvent;
use App\Events\TicketDeletedEvent;
use App\Events\TicketIssuedEvent;
use App\Events\TicketRestoredEvent;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class QueueService
{
    public function __construct(private readonly QueueSystemService $systemService) {}

    /**
     * @param  array{full_name: string, national_id: string, order_number: string}  $data
     */
    public function issueTicket(array $data): QueueTicket
    {
        $this->systemService->assertSystemOpen();
        $ticket = DB::transaction(function () use ($data): QueueTicket {
            $maxNumber = QueueTicket::query()
                ->today()
                ->lockForUpdate()
                ->max('ticket_number');

            return QueueTicket::query()->create([
                'ticket_number' => ($maxNumber ?? 0) + 1,
                'full_name' => $data['full_name'],
                'national_id' => $data['national_id'],
                'order_number' => $data['order_number'],
                'status' => TicketStatus::Waiting,
            ]);
        });

        $this->broadcastSafely(new TicketIssuedEvent($ticket));

        return $ticket;
    }

    public function callNext(User $teller): QueueTicket
    {
        $this->systemService->assertSystemOpen();

        if (! $teller->is_active) {
            throw ValidationException::withMessages([
                'teller' => 'حساب الموظف غير نشط.',
            ]);
        }

        return DB::transaction(function () use ($teller): QueueTicket {
            $nextTicket = QueueTicket::query()
                ->today()
                ->waiting()
                ->orderBy('ticket_number')
                ->lockForUpdate()
                ->first();

            if (! $nextTicket) {
                throw ValidationException::withMessages([
                    'queue' => 'لا توجد تذاكر في الانتظار.',
                ]);
            }

            $nextTicket->update([
                'status' => TicketStatus::Serving,
                'user_id' => $teller->id,
                'called_at' => now(),
            ]);

            $nextTicket->load('teller');

            $this->broadcastSafely(new TicketCalledEvent($nextTicket));

            return $nextTicket;
        });
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
        abort_unless($admin->isSuperAdmin(), 403, 'ليس لديك صلاحية للوصول.');

        $ticketId = $ticket->id;
        $ticketNumber = $ticket->ticket_number;

        $ticket->delete();

        $this->broadcastSafely(new TicketDeletedEvent($ticketId, $ticketNumber));
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

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCalledEvent($ticket));

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
            'user_id' => null,
            'called_at' => null,
            'entered_at' => null,
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

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCalledEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function markMedicalChecked(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->medical_checked_at, 'تم تسجيل الكشف الطبي لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->entered_at, 'سجّل طلب الدخول أولاً قبل الكشف الطبي.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'medical_checked_at' => now(),
        ]);

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCalledEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function markFacePrinted(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->face_printed_at, 'تم تسجيل بصمة الوجه لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->medical_checked_at, 'سجّل الكشف الطبي أولاً قبل بصمة الوجه.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'face_printed_at' => now(),
        ]);

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCalledEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function markFileDelivered(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertActiveStaff($teller);
        $this->assertTicketIsProcessable($ticket);
        $this->assertCheckpointNotAlreadySet($ticket->file_delivered_at, 'تم تسليم الملف لهذه التذكرة مسبقاً.');
        $this->assertPreviousCheckpoint($ticket->face_printed_at, 'سجّل بصمة الوجه أولاً قبل تسليم الملف.');

        $ticket->update([
            ...$this->servingAssignment($ticket, $teller),
            'file_delivered_at' => now(),
        ]);

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCalledEvent($ticket));

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
            ->orderBy('ticket_number');

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
            ->orderBy('called_at')
            ->get();

        return [
            'tickets' => $query->get(),
            'stats' => QueueTicket::todayStatCounts(),
            'serving' => $serving,
            'absent' => QueueTicket::query()
                ->today()
                ->absent()
                ->with('teller')
                ->latest('updated_at')
                ->get(),
            'current' => $serving->firstWhere('user_id', $teller->id),
        ];
    }

    /**
     * @return array{ticket: QueueTicket, people_ahead: int|null, position_in_queue: int|null}
     */
    public function trackTicket(?string $nationalId, ?string $orderNumber): array
    {
        $ticket = QueueTicket::query()
            ->today()
            ->when($nationalId, fn ($query) => $query->where('national_id', $nationalId))
            ->when($orderNumber, fn ($query) => $query->where('order_number', $orderNumber))
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
            $peopleAhead = QueueTicket::query()
                ->today()
                ->waiting()
                ->where('ticket_number', '<', $ticket->ticket_number)
                ->count();

            $positionInQueue = $peopleAhead + 1;
        }

        return [
            'ticket' => $ticket,
            'people_ahead' => $peopleAhead,
            'position_in_queue' => $positionInQueue,
        ];
    }

    /**
     * @return array{serving: Collection, waiting: Collection, stats: array<string, int>}
     */
    public function getPublicQueueStatus(): array
    {
        $serving = QueueTicket::query()
            ->today()
            ->serving()
            ->with('teller')
            ->orderBy('called_at')
            ->get();

        $waiting = QueueTicket::query()
            ->today()
            ->waiting()
            ->orderBy('ticket_number')
            ->limit(10)
            ->get();

        $aggregates = QueueTicket::todayAggregates();

        $stats = [
            'waiting' => $aggregates['waiting'],
            'serving' => $aggregates['serving'],
            'completed' => $aggregates['completed'],
        ];

        return compact('serving', 'waiting', 'stats');
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
            'called_at' => $ticket->called_at ?? now(),
        ];
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
