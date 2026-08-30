<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSystemController extends Controller
{
    public function __construct(private readonly QueueSystemService $systemService) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'system' => $this->systemService->getStatus(),
        ]);
    }

    public function close(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:255'],
        ], [
            'message.max' => 'رسالة الإغلاق طويلة جداً.',
        ]);

        $system = $this->systemService->closeSystem(
            $request->user(),
            $validated['message'] ?? null,
        );

        return response()->json([
            'message' => 'تم إغلاق النظام بنجاح.',
            'system' => $system,
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $system = $this->systemService->openSystem($request->user());

        return response()->json([
            'message' => 'تم فتح النظام بنجاح.',
            'system' => $system,
        ]);
    }

    public function resetDay(Request $request): JsonResponse
    {
        $result = $this->systemService->resetDay($request->user());

        return response()->json([
            'message' => 'تم تصفير اليوم بنجاح.',
            'deleted_tickets' => $result['deleted_tickets'],
            'reset_at' => $result['reset_at'],
            'system' => $result['system'],
        ]);
    }
}
