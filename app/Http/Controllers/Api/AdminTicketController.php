<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\AdminTicketResource;
use App\Models\QueueTicket;
use App\Models\RequestType;
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
            'request_type' => ['sometimes', 'nullable', 'string', Rule::in(['all', ...RequestType::laneValues()])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'search_by' => ['sometimes', 'nullable', 'string', Rule::in(QueueTicket::searchFieldValues())],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'step.in' => 'خطوة الطلب غير صحيحة.',
            'request_type.in' => 'نوع الطلب غير صحيح.',
            'search_by.in' => 'حقل البحث غير صحيح.',
            'date.date_format' => 'تاريخ الأرشيف غير صحيح.',
            'date.before_or_equal' => 'لا يمكن عرض تسجيلات تاريخ في المستقبل.',
        ]);

        $status = $validated['status'] ?? null;
        $step = $validated['step'] ?? null;
        $requestType = $validated['request_type'] ?? null;
        $search = $validated['search'] ?? null;
        $searchBy = $validated['search_by'] ?? null;
        $date = filled($validated['date'] ?? null)
            ? $validated['date']
            : today()->toDateString();

        $query = QueueTicket::query()
            ->onDate($date)
            ->with('teller')
            ->inQueueOrder();

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (filled($step) && $step !== 'all') {
            $query->atProcessStep($step);
        }

        if (filled($requestType) && $requestType !== 'all') {
            $query->forQueueLanes([$requestType]);
        }

        if (filled($search)) {
            $query->matchingSearch($search, $searchBy);
        }

        $tickets = $query->get();

        return response()->json([
            'date' => $date,
            'today' => today()->toDateString(),
            'is_today' => $date === today()->toDateString(),
            'available_dates' => QueueTicket::registrationDates(),
            'tickets' => AdminTicketResource::collection($tickets),
            'stats' => QueueTicket::statCountsForDate($date),
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

    public function update(UpdateTicketRequest $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->updateTicket(
            $ticket,
            $request->safe()->only([
                'full_name',
                'national_id',
                'request_type',
                'completion_step',
                'college',
                'order_number',
                'department',
                'seat_number',
            ]),
            $request->user(),
        );

        return response()->json([
            'message' => 'تم تعديل الطلب بنجاح.',
            'ticket' => new AdminTicketResource($ticket),
        ]);
    }

    public function destroy(Request $request, QueueTicket $ticket): JsonResponse
    {
        abort_unless($request->user()?->canDeleteTickets() === true, 403, 'ليس لديك صلاحية للوصول.');

        $this->queueService->deleteTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم حذف الطلب بنجاح.',
        ]);
    }

    public function restore(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->restoreCancelledTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم إرجاع الطلب الملغى لقائمة الانتظار.',
            'ticket' => new AdminTicketResource($ticket),
        ]);
    }
}
