<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTicketResource;
use App\Http\Resources\TicketResource;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TellerQueueController extends Controller
{
    public function __construct(private readonly QueueService $queueService) {}

    public function queueStatus(): JsonResponse
    {
        $status = $this->queueService->getPublicQueueStatus();

        return response()->json([
            'serving' => PublicTicketResource::collection($status['serving']),
            'waiting' => PublicTicketResource::collection($status['waiting']),
            'stats' => $status['stats'],
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
}
