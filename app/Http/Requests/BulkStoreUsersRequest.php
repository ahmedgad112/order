<?php

namespace App\Http\Requests;

use App\Enums\ProcessStep;
use App\Models\Faculty;
use App\Models\ProcessService;
use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreUsersRequest extends FormRequest
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
        return [
            'base_name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'count' => ['required', 'integer', 'min:1', 'max:50'],
            'counter_base' => ['sometimes', 'string', 'min:1', 'max:100'],
            'queue_lanes' => ['sometimes', 'array'],
            'queue_lanes.*' => ['required', 'string', 'distinct', Rule::in(RequestType::laneValues())],
            'process_steps' => ['sometimes', 'array'],
            'process_steps.*' => ['required', 'string', 'distinct', Rule::in(ProcessService::assignableValues() ?: ProcessStep::assignableValues())],
            'assigned_faculties' => ['sometimes', 'array'],
            'assigned_faculties.*' => ['required', 'string', 'distinct', Rule::in(Faculty::slugs())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_name.min' => 'يجب أن يتكون الاسم الأساسي من حرفين على الأقل.',
            'count.required' => 'عدد المستخدمين مطلوب.',
            'count.integer' => 'عدد المستخدمين يجب أن يكون رقماً صحيحاً.',
            'count.min' => 'يجب إنشاء مستخدم واحد على الأقل.',
            'count.max' => 'لا يمكن إنشاء أكثر من 50 مستخدماً في المرة الواحدة.',
            'counter_base.required' => 'اسم الشباك الأساسي مطلوب.',
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
