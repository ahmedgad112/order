<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketCalledEvent;
use App\Events\TicketCompletedEvent;
use App\Events\TicketIssuedEvent;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
            $currentServing = QueueTicket::query()
                ->today()
                ->serving()
                ->where('user_id', $teller->id)
                ->lockForUpdate()
                ->first();

            if ($currentServing) {
                throw new ConflictHttpException('لديك تذكرة قيد الخدمة بالفعل. أكملها أو ألغها أولاً.');
            }

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
        $this->assertTicketOwnedByTeller($ticket, $teller);

        if ($ticket->status !== TicketStatus::Serving) {
            throw ValidationException::withMessages([
                'ticket' => 'يمكن إكمال التذاكر قيد الخدمة فقط.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->broadcastSafely(new TicketCompletedEvent($ticket));

        return $ticket->fresh(['teller']);
    }

    public function cancelTicket(QueueTicket $ticket, User $teller): QueueTicket
    {
        $this->assertTicketOwnedByTeller($ticket, $teller);

        if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Serving], true)) {
            throw ValidationException::withMessages([
                'ticket' => 'لا يمكن إلغاء هذه التذكرة.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Cancelled,
            'completed_at' => now(),
        ]);

        $this->broadcastSafely(new TicketCompletedEvent($ticket));

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

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCalledEvent($ticket));

        return $ticket;
    }

    public function adminMarkEntered(QueueTicket $ticket): QueueTicket
    {
        if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Serving], true)) {
            throw ValidationException::withMessages([
                'ticket' => 'لا يمكن تسجيل الدخول لهذه التذكرة.',
            ]);
        }

        $ticket->update([
            'status' => TicketStatus::Completed,
            'called_at' => $ticket->called_at ?? now(),
            'completed_at' => now(),
        ]);

        $ticket->load('teller');

        $this->broadcastSafely(new TicketCompletedEvent($ticket));

        return $ticket->fresh(['teller']);
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
     * @return array{serving: \Illuminate\Support\Collection, waiting: \Illuminate\Support\Collection, stats: array<string, int>}
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

        $stats = [
            'waiting' => QueueTicket::query()->today()->waiting()->count(),
            'serving' => QueueTicket::query()->today()->serving()->count(),
            'completed' => QueueTicket::query()->today()->where('status', TicketStatus::Completed)->count(),
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

    private function broadcastSafely(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Queue broadcast failed: '.$exception->getMessage());
        }
    }
}
