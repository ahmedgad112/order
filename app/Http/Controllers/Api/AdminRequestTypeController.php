<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestTypeRequest;
use App\Http\Requests\UpdateRequestTypeRequest;
use App\Models\RequestType;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminRequestTypeController extends Controller
{
    public function __construct(private readonly QueueSystemService $systemService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'request_types' => RequestType::adminPayload(),
        ]);
    }

    public function store(StoreRequestTypeRequest $request): JsonResponse
    {
        $data = $request->validated();

        RequestType::query()->create([
            'slug' => RequestType::makeSlug($data['label']),
            'label' => $data['label'],
            'code_prefix' => RequestType::nextAvailablePrefix(),
            'college_mode' => $data['college_mode'],
            'college_label' => $data['college_label'] ?? null,
            'counter_name' => $data['counter_name'] ?? null,
            'requires_completion_service' => $data['requires_completion_service'] ?? false,
            'completion_services' => $this->completionServices($data),
            'enabled' => $data['enabled'] ?? true,
            'sort_order' => RequestType::nextSortOrder(),
        ]);

        return response()->json([
            'message' => 'تم إضافة نوع الطلب بنجاح.',
            'request_types' => RequestType::adminPayload(),
            'system' => $this->systemService->broadcastStatus(),
        ], 201);
    }

    public function update(UpdateRequestTypeRequest $request, RequestType $requestType): JsonResponse
    {
        $data = $request->validated();

        if (
            array_key_exists('enabled', $data)
            && $data['enabled'] === false
            && $requestType->enabled
            && count(RequestType::enabledSlugs()) <= 1
        ) {
            throw ValidationException::withMessages([
                'enabled' => 'يجب إبقاء نوع طلب واحد على الأقل ظاهراً.',
            ]);
        }

        $requestType->update([
            'label' => $data['label'] ?? $requestType->label,
            'college_mode' => $data['college_mode'] ?? $requestType->college_mode,
            'college_label' => $data['college_label'] ?? $requestType->college_label,
            'counter_name' => array_key_exists('counter_name', $data)
                ? $data['counter_name']
                : $requestType->counter_name,
            'requires_completion_service' => $data['requires_completion_service']
                ?? $requestType->requires_completion_service,
            'completion_services' => $this->completionServices($data, $requestType),
            'enabled' => $data['enabled'] ?? $requestType->enabled,
        ]);

        return response()->json([
            'message' => 'تم تحديث نوع الطلب بنجاح.',
            'request_types' => RequestType::adminPayload(),
            'system' => $this->systemService->broadcastStatus(),
        ]);
    }

    public function destroy(Request $request, RequestType $requestType): JsonResponse
    {
        abort_unless($request->user()?->canControlSystem() === true, 403, 'ليس لديك صلاحية للوصول.');

        if ($requestType->ticketsCount() > 0) {
            throw ValidationException::withMessages([
                'request_type' => 'لا يمكن حذف نوع طلب له تذاكر مسجلة. عطّله بدلاً من ذلك.',
            ]);
        }

        $requestType->delete();

        return response()->json([
            'message' => 'تم حذف نوع الطلب.',
            'request_types' => RequestType::adminPayload(),
            'system' => $this->systemService->broadcastStatus(),
        ]);
    }

    /**
     * Null means "all admission completion services" — see completionServiceValues().
     *
     * @param  array<string, mixed>  $data
     * @return list<string>|null
     */
    private function completionServices(array $data, ?RequestType $existing = null): ?array
    {
        $requires = $data['requires_completion_service']
            ?? $existing?->requires_completion_service
            ?? false;

        if (! $requires) {
            return null;
        }

        $services = $data['completion_services'] ?? $existing?->completion_services;

        if (! is_array($services) || $services === []) {
            return null;
        }

        return array_values($services);
    }
}
