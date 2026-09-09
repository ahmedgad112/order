<?php

namespace App\Http\Requests;

use App\Enums\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnabledRequestTypesRequest extends FormRequest
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
            'enabled_request_types' => ['required', 'array', 'min:1'],
            'enabled_request_types.*' => ['required', 'string', 'distinct', Rule::enum(RequestType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled_request_types.required' => 'يجب اختيار نوع طلب واحد على الأقل.',
            'enabled_request_types.min' => 'يجب اختيار نوع طلب واحد على الأقل.',
            'enabled_request_types.*.distinct' => 'لا يمكن تكرار نوع الطلب.',
            'enabled_request_types.*.enum' => 'نوع الطلب غير صحيح.',
        ];
    }
}
