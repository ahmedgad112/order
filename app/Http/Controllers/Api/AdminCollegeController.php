<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCollegeRequest;
use App\Http\Requests\UpdateCollegeRequest;
use App\Http\Resources\CollegeResource;
use App\Http\Resources\FacultyResource;
use App\Models\College;
use App\Models\Faculty;
use App\Services\QueueSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminCollegeController extends Controller
{
    public function __construct(private readonly QueueSystemService $systemService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->catalogPayload(false));
    }

    public function store(StoreCollegeRequest $request): JsonResponse
    {
        $data = $request->validated();

        College::query()->create([
            'name' => $data['name'],
            'slug' => College::makeSlug($data['name'], $data['value'] ?? null),
            'sort_order' => College::nextSortOrder(),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم إضافة الكلية بنجاح.',
            ...$this->catalogPayload(true),
        ], 201);
    }

    public function update(UpdateCollegeRequest $request, College $college): JsonResponse
    {
        $data = $request->validated();
        $willDeactivate = array_key_exists('is_active', $data)
            && $data['is_active'] === false
            && $college->is_active;

        if ($willDeactivate) {
            $this->assertCanDeactivateCollege($college);
        }

        $college->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? $college->is_active,
        ]);

        return response()->json([
            'message' => 'تم تحديث الكلية بنجاح.',
            ...$this->catalogPayload(true),
        ]);
    }

    public function destroy(Request $request, College $college): JsonResponse
    {
        abort_unless($request->user()?->canControlSystem() === true, 403, 'ليس لديك صلاحية للوصول.');

        $this->assertCanDeactivateCollege($college);

        $college->update(['is_active' => false]);

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

    private function assertCanDeactivateCollege(College $college): void
    {
        if (! $college->is_active) {
            return;
        }

        if (College::activeCount() <= 1) {
            throw ValidationException::withMessages([
                'college' => 'يجب إبقاء كلية واحدة على الأقل ظاهرة لبطاقة الترشيح.',
            ]);
        }
    }
}
