<?php

namespace App\Http\Controllers\Api;

use App\Enums\StudentRegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentRegistrationResource;
use App\Models\StudentRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TellerStudentRegistrationController extends Controller
{
    public function index(): JsonResponse
    {
        $registrations = StudentRegistration::query()
            ->where('status', StudentRegistrationStatus::Waiting)
            ->orderBy('service_date')
            ->orderBy('turn_number')
            ->get();

        return response()->json([
            'registrations' => StudentRegistrationResource::collection($registrations)->resolve(),
        ]);
    }

    public function complete(Request $request, StudentRegistration $studentRegistration): JsonResponse
    {
        if ($studentRegistration->status === StudentRegistrationStatus::Completed) {
            throw ValidationException::withMessages([
                'registration' => 'تم إنهاء هذا الدور من قبل.',
            ]);
        }

        $studentRegistration->update([
            'status' => StudentRegistrationStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'تم إنهاء الدور.',
            'registration' => new StudentRegistrationResource($studentRegistration->fresh()),
        ]);
    }
}
