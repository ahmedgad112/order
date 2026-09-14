<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFacultyRequest;
use App\Http\Requests\UpdateFacultyRequest;
use App\Http\Resources\CollegeResource;
use App\Http\Resources\FacultyResource;
use App\Models\College;
use App\Models\Faculty;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminFacultyController extends Controller
{
    public function __construct(private readonly QueueSystemService $systemService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->catalogPayload(false));
    }

    public function store(StoreFacultyRequest $request): JsonResponse
    {
        $data = $request->validated();

        Faculty::query()->create([
            'name' => $data['name'],
            'slug' => Faculty::makeSlug($data['name'], $data['value'] ?? null),
            'seat_number_min_digits' => $data['seat_number_min_digits'],
            'seat_number_max_digits' => $data['seat_number_max_digits'],
            'sort_order' => Faculty::nextSortOrder(),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم إضافة الكلية بنجاح.',
            ...$this->catalogPayload(true),
        ], 201);
    }

    public function update(UpdateFacultyRequest $request, Faculty $faculty): JsonResponse
    {
        $data = $request->validated();
        $willDeactivate = array_key_exists('is_active', $data)
            && $data['is_active'] === false
            && $faculty->is_active;

        if ($willDeactivate) {
            $this->assertCanDeactivateFaculty($faculty);
        }

        $faculty->update([
            'name' => $data['name'],
            'seat_number_min_digits' => $data['seat_number_min_digits'],
            'seat_number_max_digits' => $data['seat_number_max_digits'],
            'is_active' => $data['is_active'] ?? $faculty->is_active,
        ]);

        return response()->json([
            'message' => 'تم تحديث الكلية بنجاح.',
            ...$this->catalogPayload(true),
        ]);
    }

    public function destroy(Request $request, Faculty $faculty): JsonResponse
    {
        abort_unless($request->user()?->canControlSystem() === true, 403, 'ليس لديك صلاحية للوصول.');

        $this->assertCanDeactivateFaculty($faculty);

        $faculty->update(['is_active' => false]);

        return response()->json([
            'message' => 'تم إخفاء الكلية من شاشات التسجيل.',
            ...$this->catalogPayload(true),
        ]);
    }

    /**
     * @return array{faculties: mixed, colleges: mixed, system: array<string, mixed>}
     */
    private function catalogPayload(bool $broadcast): array
    {
        return [
            'faculties' => FacultyResource::collection(Faculty::query()->ordered()->get()),
            'colleges' => CollegeResource::collection(College::query()->ordered()->get()),
            'system' => $broadcast
                ? $this->systemService->broadcastStatus()
                : $this->systemService->getStatus(),
        ];
    }

    private function assertCanDeactivateFaculty(Faculty $faculty): void
    {
        if (! $faculty->is_active) {
            return;
        }

        if (Faculty::activeCount() <= 1) {
            throw ValidationException::withMessages([
                'faculty' => 'يجب إبقاء كلية واحدة على الأقل ظاهرة للطلاب الحاليين.',
            ]);
        }
    }
}
