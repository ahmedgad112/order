<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueTicketRequest;
use App\Http\Requests\TrackTicketRequest;
use App\Http\Resources\IssuedTicketResource;
use App\Http\Resources\PublicTicketResource;
use App\Http\Resources\ScannedTicketResource;
use App\Http\Resources\UserTicketResource;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;

class PublicQueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly QueueSystemService $systemService,
    ) {}

    public function issueTicket(IssueTicketRequest $request): JsonResponse
    {
        $ticket = $this->queueService->issueTicket($request->validated());

        return response()->json([
            'message' => 'تم إصدار التذكرة بنجاح.',
            'ticket' => new IssuedTicketResource($ticket),
        ], 201);
    }

    public function showTicket(QueueTicket $ticket): JsonResponse
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

    public function trackTicket(TrackTicketRequest $request): JsonResponse
    {
        $result = $this->queueService->trackTicket(
            $request->input('national_id'),
            $request->input('order_number'),
        );

        return response()->json([
            'ticket' => new UserTicketResource(
                $result['ticket'],
                $result['people_ahead'],
                $result['position_in_queue'],
            ),
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function queueStatus(): JsonResponse
    {
        $status = $this->queueService->getPublicQueueStatus();

        return response()->json([
            'serving' => PublicTicketResource::collection($status['serving']),
            'waiting' => PublicTicketResource::collection($status['waiting']),
            'stats' => $status['stats'],
            'system' => $this->systemService->getStatus(),
        ]);
    }
}
