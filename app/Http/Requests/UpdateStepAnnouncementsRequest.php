<?php

namespace App\Http\Requests;

use App\Enums\ProcessStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStepAnnouncementsRequest extends FormRequest
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
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step' => ['required', 'string', 'distinct', Rule::in(ProcessStep::values())],
            'steps.*.enabled' => ['required', 'boolean'],
            'steps.*.destination' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.required' => 'يجب تحديد خطوات النداء.',
            'steps.*.step.in' => 'خطوة النداء غير صحيحة.',
            'steps.*.step.distinct' => 'لا يمكن تكرار خطوة النداء.',
            'steps.*.destination.max' => 'وجهة النداء طويلة جداً.',
        ];
    }
}
