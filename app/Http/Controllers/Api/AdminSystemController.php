<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCallTemplateRequest;
use App\Http\Requests\UpdateEnabledRequestTypesRequest;
use App\Http\Requests\UpdateEnabledStudentKindsRequest;
use App\Http\Requests\UpdateQueueLaneTellersRequest;
use App\Models\QueueSystemSetting;
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

    public function endDay(Request $request): JsonResponse
    {
        $result = $this->systemService->endDay($request->user());

        return response()->json([
            'message' => 'تم إنهاء اليوم وأرشفة الطلبات. النظام ما زال يعمل بدون استقبال طلبات جديدة.',
            'archived_tickets' => $result['archived_tickets'],
            'ended_at' => $result['ended_at'],
            'system' => $result['system'],
        ]);
    }

    public function openDay(Request $request): JsonResponse
    {
        $result = $this->systemService->openDay($request->user());

        return response()->json([
            'message' => 'تم فتح يوم جديد. العداد يبدأ من الصفر.',
            'opened_at' => $result['opened_at'],
            'system' => $result['system'],
        ]);
    }

    public function updateRequestTypes(UpdateEnabledRequestTypesRequest $request): JsonResponse
    {
        $system = $this->systemService->updateEnabledRequestTypes(
            $request->user(),
            $request->validated('enabled_request_types'),
        );

        return response()->json([
            'message' => 'تم تحديث أنواع الطلبات المتاحة.',
            'system' => $system,
        ]);
    }

    public function updateStudentKinds(UpdateEnabledStudentKindsRequest $request): JsonResponse
    {
        $system = $this->systemService->updateEnabledStudentKinds(
            $request->user(),
            $request->validated('enabled_student_kinds'),
        );

        return response()->json([
            'message' => 'تم تحديث أنواع الطلاب في شاشة الاختيار.',
            'system' => $system,
        ]);
    }

    public function updateCallTemplate(UpdateCallTemplateRequest $request): JsonResponse
    {
        $template = trim((string) ($request->validated('call_template') ?? ''));

        QueueSystemSetting::current()->update([
            'call_template' => $template === '' ? null : $template,
        ]);

        return response()->json([
            'message' => 'تم حفظ نص النداء.',
            'system' => $this->systemService->broadcastStatus(),
        ]);
    }

    public function updateLaneTellers(UpdateQueueLaneTellersRequest $request, string $lane): JsonResponse
    {
        $queueLanes = $this->systemService->assignTellersToLane(
            $lane,
            $request->validated('teller_ids'),
        );

        return response()->json([
            'message' => 'تم تحديث موظفي نوع الطلب.',
            'queue_lanes' => $queueLanes,
        ]);
    }
}
