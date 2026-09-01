<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class AdminReportController extends Controller
{
    public function __construct(private readonly QueueSystemService $systemService) {}

    public function dashboard(): JsonResponse
    {
        $performance = $this->tellerPerformancePayload();

        return response()->json([
            'date' => today()->toDateString(),
            'metrics' => $this->dailyMetricsPayload(),
            'teller_performance' => $performance,
            'tellers' => $performance,
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function dailyMetrics(): JsonResponse
    {
        return response()->json([
            'date' => today()->toDateString(),
            'metrics' => $this->dailyMetricsPayload(),
        ]);
    }

    public function tellerPerformance(): JsonResponse
    {
        return response()->json([
            'tellers' => $this->tellerPerformancePayload(),
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

    /**
     * @return array{
     *     total: int,
     *     waiting: int,
     *     serving: int,
     *     completed: int,
     *     cancelled: int,
     *     avg_wait_seconds: int,
     *     avg_handling_seconds: int
     * }
     */
    private function dailyMetricsPayload(): array
    {
        $aggregates = QueueTicket::todayAggregates();

        return [
            'total' => $aggregates['total'],
            'waiting' => $aggregates['waiting'],
            'serving' => $aggregates['serving'],
            'completed' => $aggregates['completed'],
            'cancelled' => $aggregates['cancelled'],
            'avg_wait_seconds' => $aggregates['avg_wait_seconds'],
            'avg_handling_seconds' => $aggregates['avg_handling_seconds'],
        ];
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     counter_name: string|null,
     *     is_active: bool,
     *     completed_today: int,
     *     serving_now: int,
     *     avg_handling_seconds: int
     * }>
     */
    private function tellerPerformancePayload(): Collection
    {
        $handleSql = QueueTicket::durationSecondsSql('called_at', 'completed_at');

        $stats = QueueTicket::query()
            ->today()
            ->whereNotNull('user_id')
            ->toBase()
            ->selectRaw('user_id')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as completed_today', [TicketStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as serving_now', [TicketStatus::Serving->value])
            ->selectRaw(
                "AVG(CASE WHEN status = ? AND called_at IS NOT NULL AND completed_at IS NOT NULL THEN {$handleSql} END) as avg_handling",
                [TicketStatus::Completed->value],
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return User::query()
            ->where('role', UserRole::Teller)
            ->orderBy('name')
            ->get()
            ->map(function (User $teller) use ($stats): array {
                $row = $stats->get($teller->id);

                return [
                    'id' => $teller->id,
                    'name' => $teller->name,
                    'counter_name' => $teller->counter_name,
                    'is_active' => $teller->is_active,
                    'completed_today' => (int) ($row->completed_today ?? 0),
                    'serving_now' => (int) ($row->serving_now ?? 0),
                    'avg_handling_seconds' => (int) round((float) ($row->avg_handling ?? 0)),
                ];
            });
    }
}
