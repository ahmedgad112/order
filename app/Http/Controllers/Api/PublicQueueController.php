<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackTicketRequest;
use App\Http\Resources\UserTicketResource;
use App\Services\QueueService;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;

class PublicQueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly QueueSystemService $systemService,
    ) {}

    public function issueTicket(): JsonResponse
    {
        abort(403, 'التسجيل يتم عن طريق الموظف.');
    }

    public function trackTicket(TrackTicketRequest $request): JsonResponse
    {
        $result = $this->queueService->trackTicket(
            $request->input('national_id'),
            $request->input('order_number'),
            $request->input('seat_number'),
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
            'serving' => $status['serving'],
            'waiting' => $status['waiting'],
            'stats' => $status['stats'],
            'system' => $this->systemService->getStatus(),
        ]);
    }
}
