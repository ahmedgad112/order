<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCompleteProcessServiceRequest;
use App\Http\Requests\IssueTicketRequest;
use App\Http\Resources\PublicTicketResource;
use App\Http\Resources\ScannedTicketResource;
use App\Http\Resources\TicketResource;
use App\Models\ProcessService;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Services\QueueService;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TellerQueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly QueueSystemService $systemService,
    ) {}

    public function store(IssueTicketRequest $request): JsonResponse
    {
        $payload = $request->safe()->except(['count']);
        $count = (int) ($request->validated('count') ?? 1);
        $tickets = $this->queueService->issueTickets($payload, $request->user(), $count);

        return response()->json([
            'message' => $tickets->count() === 1
                ? 'تم إصدار الدور بنجاح.'
                : 'تم إصدار '.$tickets->count().' أدوار بنجاح.',
            'ticket' => new TicketResource($tickets->first()),
            'tickets' => TicketResource::collection($tickets),
        ], 201);
    }

    public function tickets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'step' => ['sometimes', 'nullable', 'string', Rule::in(['all', ...QueueTicket::processStepValues()])],
            'request_type' => ['sometimes', 'nullable', 'string', Rule::in(['all', ...RequestType::laneValues()])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'search_by' => ['sometimes', 'nullable', 'string', Rule::in(QueueTicket::searchFieldValues())],
        ], [
            'step.in' => 'خطوة الطلب غير صحيحة.',
            'request_type.in' => 'نوع الطلب غير صحيح.',
            'search_by.in' => 'حقل البحث غير صحيح.',
        ]);

        $result = $this->queueService->getTellerTickets(
            $request->user(),
            filled($validated['status'] ?? null) ? $validated['status'] : null,
            filled($validated['search'] ?? null) ? $validated['search'] : null,
            filled($validated['step'] ?? null) ? $validated['step'] : null,
            filled($validated['request_type'] ?? null) ? $validated['request_type'] : null,
            filled($validated['search_by'] ?? null) ? $validated['search_by'] : null,
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

    public function showDocument(QueueTicket $ticket): StreamedResponse
    {
        abort_if(! $ticket->hasDocument() || ! Storage::exists((string) $ticket->document_path), 404);

        return Storage::response((string) $ticket->document_path);
    }

    public function markEntered(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markEntered($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل طلب الدخول.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markPaid(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markPaid($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل الدفع.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markFileWithdrawn(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markFileWithdrawn($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل سحب الملف.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markDocumentsReviewed(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->markDocumentsReviewed($ticket, $request->user());

        return response()->json([
            'message' => 'تم تسجيل مراجعة الورق.',
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

    public function markService(Request $request, QueueTicket $ticket, string $serviceSlug): JsonResponse
    {
        $processService = ProcessService::findBySlug($serviceSlug);

        abort_if($processService === null, 404);

        $ticket = $this->queueService->markProcessService($ticket, $processService, $request->user());

        return response()->json([
            'message' => 'تم تسجيل الخدمة.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function markServiceBulk(BulkCompleteProcessServiceRequest $request, string $serviceSlug): JsonResponse
    {
        $processService = ProcessService::findBySlug($serviceSlug);

        abort_if($processService === null, 404);

        $result = $this->queueService->markProcessServiceBulk(
            $request->user(),
            $processService,
            $request->validated('ticket_ids'),
        );

        $message = match (true) {
            $result['updated_count'] === 0 => 'لم يتم تطبيق العملية على أي تذكرة.',
            $result['failed_count'] === 0 => 'تم تطبيق العملية على '.$result['updated_count'].' تذكرة.',
            default => 'تم تطبيق العملية على '.$result['updated_count'].' تذكرة، وتعذر على '.$result['failed_count'].'.',
        };

        return response()->json([
            'message' => $message,
            'updated_count' => $result['updated_count'],
            'failed_count' => $result['failed_count'],
            'failed' => $result['failed'],
            'tickets' => TicketResource::collection($result['updated']),
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

    public function restartCalling(Request $request): JsonResponse
    {
        $restored = $this->queueService->restartCalling($request->user());

        return response()->json([
            'message' => $restored > 0
                ? 'تم إلغاء النداءات وإرجاع '.$restored.' تذكرة لقائمة الانتظار.'
                : 'تم إلغاء النداءات — لا توجد تذاكر قيد الخدمة.',
            'restored_count' => $restored,
        ]);
    }

    public function callTicket(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->callTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم نداء التذكرة.',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    public function skipTicket(Request $request, QueueTicket $ticket): JsonResponse
    {
        $ticket = $this->queueService->skipTicket($ticket, $request->user());

        return response()->json([
            'message' => 'تم تخطي التذكرة — رجعت 5 مراكز في الطابور.',
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
