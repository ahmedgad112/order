<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTicketResource;
use App\Http\Resources\ScannedTicketResource;
use App\Http\Resources\TicketResource;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TellerQueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly QueueSystemService $systemService,
    ) {}

    public function tickets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'step' => ['sometimes', 'nullable', 'string', Rule::in(['all', ...QueueTicket::processStepValues()])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], [
            'step.in' => 'خطوة الطلب غير صحيحة.',
        ]);

        $result = $this->queueService->getTellerTickets(
            $request->user(),
            filled($validated['status'] ?? null) ? $validated['status'] : null,
            filled($validated['search'] ?? null) ? $validated['search'] : null,
            filled($validated['step'] ?? null) ? $validated['step'] : null,
        );

        return response()->json([
            'tickets' => TicketResource::collection($result['tickets']),
            'stats' => $result['stats'],
            'serving' => TicketResource::collection($result['serving']),
            'absent_tickets' => TicketResource::collection($result['absent']),
            'current_ticket' => $result['current'] ? new TicketResource($result['current']) : null,
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function showScannedTicket(QueueTicket $ticket): JsonResponse
    {
        $result = $this->queueService->scanTicket($ticket);

        return response()->json([
            'ticket' => new ScannedTicketResource(
                $result['ticket'],
                $result['people_ahead'],
                $result['position_in_queue'],
            ),
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function markEntered(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markEntered($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل طلب الدخول.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markMedicalChecked(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markMedicalChecked($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل الكشف الطبي.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markFacePrinted(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markFacePrinted($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل بصمة الوجه.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markFileDelivered(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markFileDelivered($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسليم الملف.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function queueStatus(): JsonResponse
    {
        $status = $this->queueService->getPublicQueueStatus();

        return response()->json([
            'serving' => TicketResource::collection($status['serving']),
            'waiting' => PublicTicketResource::collection($status['waiting']),
            'stats' => $status['stats'],
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function currentTicket(Request $request): JsonResponse
    {
        $ticket = QueueTicket::query()
            ->today()
            ->serving()
            ->where('user_id', $request->user()->id)
            ->with('teller')
            ->first();

        return response()->json([
            'ticket' => $ticket ? new TicketResource($ticket) : null,
        ]);
    }

    public function callNext(Request $request): JsonResponse
    {
        $ticket = $this->queueService->callNext($request->user());

        return response()->json([
            'message' => 'تم نداء التذكرة التالية.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function completeTicket(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->completeTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم إكمال التذكرة.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function cancelTicket(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->cancelTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم إلغاء التذكرة.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function recallTicket(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->recallTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم إعادة نداء التذكرة.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function absentTickets(): JsonResponse
    {
        $tickets = $this->queueService->getAbsentTickets();

        return response()->json([
            'tickets' => TicketResource::collection($tickets),
        ]);
    }

    public function markAbsent(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markAbsent($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل التذكرة كـ "مش موجود".',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function restoreTicket(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->restoreTicket($ticket);

        return response()->json([
            'message' => 'تم إرجاع التذكرة لقائمة الانتظار.',
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
