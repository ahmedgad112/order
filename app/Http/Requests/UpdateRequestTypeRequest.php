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
            'college_mode.in' => 'طريقة إدخال الكلية غير صحيحة.',
            'completion_services.*.in' => 'إحدى خدمات الاستكمال غير صحيحة.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('label') && is_string($this->label)) {
            $this->merge([
                'label' => trim($this->label),
            ]);
        }
    }
}
