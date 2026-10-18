<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRegistrationRequest;
use App\Http\Resources\StudentRegistrationResource;
use App\Models\StudentRegistration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PublicStudentRegistrationController extends Controller
{
    public function store(StoreStudentRegistrationRequest $request): JsonResponse
    {
        $data = $request->safe()->only([
            'full_name',
            'academic_number',
            'national_id',
            'department',
            'section_number',
            'student_group',
        ]);

        try {
            $registration = StudentRegistration::issue($data);
        } catch (UniqueConstraintViolationException) {
            if (StudentRegistration::query()->where('academic_number', $data['academic_number'])->exists()) {
                throw ValidationException::withMessages([
                    'academic_number' => 'هذا الرقم الأكاديمي مسجل بالفعل.',
                ]);
            }

            if (StudentRegistration::query()->where('national_id', $data['national_id'])->exists()) {
                throw ValidationException::withMessages([
                    'national_id' => 'هذا الرقم القومي مسجل بالفعل.',
                ]);
            }

            throw ValidationException::withMessages([
                'turn_number' => 'تعذر إصدار الدور. حاول مرة أخرى.',
            ]);
        }

        return response()->json([
            'message' => 'تم تسجيل بياناتك بنجاح.',
            'registration' => new StudentRegistrationResource($registration),
        ], 201);
    }
}
