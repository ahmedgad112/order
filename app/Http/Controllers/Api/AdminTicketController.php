<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTicketResource;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function __construct(private readonly QueueService $queueService) {}

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

        $stats = [
            'total' => QueueTicket::query()->today()->count(),
            'waiting' => QueueTicket::query()->today()->waiting()->count(),
            'serving' => QueueTicket::query()->today()->serving()->count(),
            'completed' => QueueTicket::query()->today()->where('status', TicketStatus::Completed)->count(),
            'cancelled' => QueueTicket::query()->today()->where('status', TicketStatus::Cancelled)->count(),
        ];

        return response()->json([
            'tickets' => AdminTicketResource::collection($tickets),
            'stats' => $stats,
        ]);
    }

    public function markEntered(QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->adminMarkEntered($ticket);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'ticket' => new AdminTicketResource($ticket),
        ]);
    }
}
