<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
class AdminReportController extends Controller
{
    public function dailyMetrics(): JsonResponse
    {
        $todayTickets = QueueTicket::query()->today();

        $total = (clone $todayTickets)->count();
        $waiting = (clone $todayTickets)->waiting()->count();
        $serving = (clone $todayTickets)->serving()->count();
        $completed = (clone $todayTickets)->where('status', TicketStatus::Completed)->count();
        $cancelled = (clone $todayTickets)->where('status', TicketStatus::Cancelled)->count();

        $avgWaitSeconds = (clone $todayTickets)
            ->whereNotNull('called_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, called_at)) as avg_wait')
            ->value('avg_wait');

        $avgHandlingSeconds = (clone $todayTickets)
            ->where('status', TicketStatus::Completed)
            ->whereNotNull('called_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, called_at, completed_at)) as avg_handling')
            ->value('avg_handling');

        if (config('database.default') === 'sqlite') {
            $avgWaitSeconds = (clone $todayTickets)
                ->whereNotNull('called_at')
                ->get()
                ->avg(fn (QueueTicket $ticket) => $ticket->created_at?->diffInSeconds($ticket->called_at));

            $avgHandlingSeconds = (clone $todayTickets)
                ->where('status', TicketStatus::Completed)
                ->whereNotNull('called_at')
                ->whereNotNull('completed_at')
                ->get()
                ->avg(fn (QueueTicket $ticket) => $ticket->called_at?->diffInSeconds($ticket->completed_at));
        }

        return response()->json([
            'date' => today()->toDateString(),
            'metrics' => [
                'total' => $total,
                'waiting' => $waiting,
                'serving' => $serving,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'avg_wait_seconds' => round((float) ($avgWaitSeconds ?? 0)),
                'avg_handling_seconds' => round((float) ($avgHandlingSeconds ?? 0)),
            ],
        ]);
    }

    public function tellerPerformance(): JsonResponse
    {
        $tellers = User::query()
            ->where('role', UserRole::Teller)
            ->withCount([
                'queueTickets as completed_today' => fn ($query) => $query
                    ->today()
                    ->where('status', TicketStatus::Completed),
                'queueTickets as serving_now' => fn ($query) => $query
                    ->today()
                    ->where('status', TicketStatus::Serving),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $teller): array {
                $avgHandling = QueueTicket::query()
                    ->today()
                    ->where('user_id', $teller->id)
                    ->where('status', TicketStatus::Completed)
                    ->whereNotNull('called_at')
                    ->whereNotNull('completed_at')
                    ->get()
                    ->avg(fn (QueueTicket $ticket) => $ticket->called_at?->diffInSeconds($ticket->completed_at));

                return [
                    'id' => $teller->id,
                    'name' => $teller->name,
                    'counter_name' => $teller->counter_name,
                    'is_active' => $teller->is_active,
                    'completed_today' => $teller->completed_today,
                    'serving_now' => $teller->serving_now,
                    'avg_handling_seconds' => round((float) ($avgHandling ?? 0)),
                ];
            });

        return response()->json([
            'tellers' => $tellers,
        ]);
    }

    public function tellers(): JsonResponse
    {
        $tellers = User::query()
            ->where('role', UserRole::Teller)
            ->orderBy('name')
            ->get();

        return response()->json([
            'tellers' => UserResource::collection($tellers),
        ]);
    }
}
