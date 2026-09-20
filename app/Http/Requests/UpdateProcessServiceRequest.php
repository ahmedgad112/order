<?php

namespace App\Http\Requests;

use App\Models\ProcessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcessServiceRequest extends FormRequest
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
            'label' => ['sometimes', 'string', 'min:2', 'max:100'],
            'flow' => ['sometimes', 'string', Rule::in([
                ProcessService::FLOW_ADMISSION,
                ProcessService::FLOW_CURRENT_STUDENT,
                ProcessService::FLOW_BOTH,
            ])],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.min' => 'يجب أن يتكون اسم الخدمة من حرفين على الأقل.',
            'flow.in' => 'نطاق الخدمة غير صحيح.',
        ];
    }
}
