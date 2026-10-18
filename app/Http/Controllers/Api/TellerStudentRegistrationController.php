<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentRegistrationResource;
use App\Models\StudentRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TellerStudentRegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) ($request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ])['search'] ?? ''));

        $registrations = $search === ''
            ? $this->todayRoster()
            : StudentRegistration::query()
                ->matchingRoster('', null, $search)
                ->get();

        return response()->json([
            'registrations' => StudentRegistrationResource::collection($registrations)->resolve(),
        ]);
    }

    public function complete(Request $request, StudentRegistration $studentRegistration): JsonResponse
    {
        $studentRegistration = $studentRegistration->markCompletedBy($request->user()?->id);

        return response()->json([
            'message' => 'تم إنهاء الدور.',
            'registration' => new StudentRegistrationResource($studentRegistration),
        ]);
    }

    public function markAbsent(Request $request, StudentRegistration $studentRegistration): JsonResponse
    {
        $studentRegistration = $studentRegistration->markAbsentBy($request->user()?->id);

        return response()->json([
            'message' => 'تم تسجيل الطالب كـ "مش موجود".',
            'registration' => new StudentRegistrationResource($studentRegistration),
        ]);
    }

    /**
     * @return Collection<int, StudentRegistration>
     */
    private function todayRoster(): Collection
    {
        return StudentRegistration::query()
            ->whereDate('service_date', StudentRegistration::currentServiceDate())
            ->orderBy('turn_number')
            ->orderBy('id')
            ->get();
    }
}
