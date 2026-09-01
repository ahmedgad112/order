<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTicketResource;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly QueueSystemService $systemService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = QueueTicket::query()
            ->today()
            ->with('teller')
            ->orderBy('ticket_number');

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        $tickets = $query->get();

        return response()->json([
            'tickets' => AdminTicketResource::collection($tickets),
            'stats' => QueueTicket::todayStatCounts(),
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function markEntered(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markEntered($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل طلب الدخول.',
            'ticket' => new AdminTicketResource($ticket),
        ]);
    }
}
