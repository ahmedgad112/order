<?php

namespace App\Http\Requests;

use App\Enums\ProcessStep;
use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\ProcessService;
use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', Password::min(8)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'counter_name' => ['nullable', 'string', 'max:100'],
            'queue_lanes' => ['sometimes', 'array'],
            'queue_lanes.*' => ['required', 'string', 'distinct', Rule::in(RequestType::laneValues())],
            'process_steps' => ['sometimes', 'array'],
            'process_steps.*' => ['required', 'string', 'distinct', Rule::in(ProcessService::assignableValues() ?: ProcessStep::assignableValues())],
            'assigned_faculties' => ['sometimes', 'array'],
            'assigned_faculties.*' => ['required', 'string', 'distinct', Rule::in(Faculty::slugs())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'يجب أن يتكون الاسم من 3 أحرف على الأقل.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صالح.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'queue_lanes.array' => 'أنواع الطلب المخصصة غير صحيحة.',
            'queue_lanes.*.distinct' => 'لا يمكن تكرار نوع الطلب.',
            'queue_lanes.*.in' => 'نوع الطلب غير صحيح.',
            'process_steps.array' => 'العمليات المخصصة غير صحيحة.',
            'process_steps.*.distinct' => 'لا يمكن تكرار العملية.',
            'process_steps.*.in' => 'العملية غير صحيحة.',
            'assigned_faculties.array' => 'الكليات المخصصة غير صحيحة.',
            'assigned_faculties.*.distinct' => 'لا يمكن تكرار الكلية.',
            'assigned_faculties.*.in' => 'الكلية غير صحيحة.',
        ];
    }
}
