<?php

namespace App\Http\Requests;

use App\Enums\StudentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnabledStudentKindsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canControlSystem() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled_student_kinds' => ['present', 'array'],
            'enabled_student_kinds.*' => ['required', 'string', 'distinct', Rule::in(StudentKind::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled_student_kinds.present' => 'يجب تحديد أنواع الطلاب المتاحة.',
            'enabled_student_kinds.array' => 'يجب تحديد أنواع الطلاب المتاحة.',
            'enabled_student_kinds.*.distinct' => 'لا يمكن تكرار نوع الطالب.',
            'enabled_student_kinds.*.in' => 'نوع الطالب غير صحيح.',
        ];
    }
}
