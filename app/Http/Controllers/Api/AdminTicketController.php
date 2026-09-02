<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTicketResource;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTicketController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly QueueSystemService $systemService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'step' => ['sometimes', 'nullable', 'string', Rule::in(['all', ...QueueTicket::processStepValues()])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], [
            'step.in' => 'خطوة الطلب غير صحيحة.',
        ]);

        $status = $validated['status'] ?? null;
        $step = $validated['step'] ?? null;
        $search = $validated['search'] ?? null;

        $query = QueueTicket::query()
            ->today()
            ->with('teller')
            ->orderBy('ticket_number');

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (filled($step) && $step !== 'all') {
            $query->atProcessStep($step);
        }

        if (filled($search)) {
            $query->matchingSearch($search);
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

    public function destroy(Request $request, QueueTicket $ticket): JsonResponse
    {
        $this->queueService->deleteTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم حذف الطلب بنجاح.',
        ]);
    }
}
