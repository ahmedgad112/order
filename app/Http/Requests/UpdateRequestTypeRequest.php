<?php

namespace App\Http\Requests;

use App\Enums\ProcessStep;
use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestTypeRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'code_prefix' => ['sometimes', 'required', ...RequestType::prefixRules($this->route('request_type')?->id)],
            'college_mode' => ['sometimes', 'required', 'string', Rule::in(RequestType::COLLEGE_MODES)],
            'college_label' => ['nullable', 'string', 'max:255'],
            'counter_name' => ['nullable', 'string', 'max:100'],
            'requires_completion_service' => ['sometimes', 'boolean'],
            'completion_services' => ['nullable', 'array'],
            'completion_services.*' => [
                'string',
                Rule::in(ProcessStep::admissionCompletionValues()),
            ],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'اسم نوع الطلب مطلوب.',
            'label.min' => 'اسم نوع الطلب قصير جداً.',
            'code_prefix.required' => 'رمز نوع الطلب مطلوب.',
            'code_prefix.regex' => 'رمز نوع الطلب يجب أن يكون من 1 إلى 4 حروف إنجليزية.',
            'code_prefix.min' => 'رمز نوع الطلب يجب أن يكون من 1 إلى 4 حروف إنجليزية.',
            'code_prefix.max' => 'رمز نوع الطلب يجب أن يكون من 1 إلى 4 حروف إنجليزية.',
            'code_prefix.unique' => 'رمز نوع الطلب مستخدم بالفعل.',
            'code_prefix.not_in' => 'هذا الرمز محجوز لتذاكر الطلاب.',
            'college_mode.in' => 'طريقة إدخال الكلية غير صحيحة.',
            'completion_services.*.in' => 'إحدى خدمات الاستكمال غير صحيحة.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('label') && is_string($this->label)) {
            $payload['label'] = trim($this->label);
        }

        if ($this->exists('code_prefix')) {
            $payload['code_prefix'] = RequestType::normalizePrefix(
                is_string($this->code_prefix) ? $this->code_prefix : null,
            );
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
