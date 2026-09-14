<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollegeRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'value' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الكلية مطلوب.',
            'name.min' => 'يجب أن يتكون اسم الكلية من حرفين على الأقل.',
            'value.regex' => 'رمز الكلية يجب أن يحتوي على حروف إنجليزية أو أرقام أو شرطة فقط.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('name') && is_string($this->name)) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        if ($this->exists('value') && is_string($this->value)) {
            $this->merge([
                'value' => trim($this->value),
            ]);
        }
    }
}
