<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacultyRequest extends FormRequest
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
            'seat_number_min_digits' => ['required', 'integer', 'min:1', 'max:20'],
            'seat_number_max_digits' => ['required', 'integer', 'min:1', 'max:20', 'gte:seat_number_min_digits'],
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
            'seat_number_min_digits.required' => 'الحد الأدنى لأرقام الجلوس مطلوب.',
            'seat_number_min_digits.min' => 'الحد الأدنى لأرقام الجلوس يجب ألا يقل عن 1.',
            'seat_number_max_digits.required' => 'الحد الأقصى لأرقام الجلوس مطلوب.',
            'seat_number_max_digits.gte' => 'الحد الأقصى لأرقام الجلوس يجب ألا يقل عن الحد الأدنى.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('name') && is_string($this->name)) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }
}
