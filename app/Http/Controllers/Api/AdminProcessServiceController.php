<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcessServiceRequest;
use App\Http\Requests\UpdateProcessServiceRequest;
use App\Models\ProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProcessServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canControlSystem() === true, 403, 'ليس لديك صلاحية للوصول.');

        return response()->json([
            'process_services' => ProcessService::adminPayload(),
        ]);
    }

    public function store(StoreProcessServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $maxOrder = (int) ProcessService::query()->max('sort_order');

        $service = ProcessService::query()->create([
            'label' => $data['label'],
            'slug' => ProcessService::uniqueSlugFromLabel($data['label']),
            'system_key' => null,
            'flow' => $data['flow'] ?? ProcessService::FLOW_BOTH,
            'is_system' => false,
            'is_enabled' => $data['is_enabled'] ?? true,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 10),
        ]);

        return response()->json([
            'message' => 'تم إنشاء الخدمة بنجاح.',
            'process_service' => $service->toAdminArray(),
            'process_services' => ProcessService::adminPayload(),
        ], 201);
    }

    public function update(UpdateProcessServiceRequest $request, ProcessService $processService): JsonResponse
    {
        $data = $request->validated();

        $processService->update([
            'label' => $data['label'] ?? $processService->label,
            'flow' => $data['flow'] ?? $processService->flow,
            'is_enabled' => array_key_exists('is_enabled', $data)
                ? (bool) $data['is_enabled']
                : $processService->is_enabled,
            'sort_order' => $data['sort_order'] ?? $processService->sort_order,
        ]);

        return response()->json([
            'message' => 'تم تحديث الخدمة بنجاح.',
            'process_service' => $processService->fresh()->toAdminArray(),
            'process_services' => ProcessService::adminPayload(),
        ]);
    }

    public function destroy(Request $request, ProcessService $processService): JsonResponse
    {
        abort_unless($request->user()?->canControlSystem() === true, 403, 'ليس لديك صلاحية للوصول.');

        $processService->delete();

        return response()->json([
            'message' => 'تم حذف الخدمة بنجاح.',
            'process_services' => ProcessService::adminPayload(),
        ]);
    }
}
