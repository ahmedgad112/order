<?php

namespace App\Http\Requests;

use App\Enums\StudentRegistrationGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'academic_number' => ['required', 'regex:/^\d{4,20}$/', Rule::unique('student_registrations', 'academic_number')],
            'national_id' => ['required', 'digits:14', Rule::unique('student_registrations', 'national_id')],
            'department' => ['required', 'string', 'min:2', 'max:100'],
            'section_number' => ['required', 'integer', 'min:1', 'max:999'],
            'student_group' => ['required', Rule::in(StudentRegistrationGroup::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'اسم الطالب مطلوب.',
            'full_name.min' => 'يجب أن يتكون الاسم من 3 أحرف على الأقل.',
            'academic_number.required' => 'الرقم الأكاديمي مطلوب.',
            'academic_number.regex' => 'يجب أن يتكون الرقم الأكاديمي من 4 إلى 20 رقمًا.',
            'academic_number.unique' => 'هذا الرقم الأكاديمي مسجل بالفعل.',
            'national_id.required' => 'الرقم القومي مطلوب.',
            'national_id.digits' => 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.',
            'national_id.unique' => 'هذا الرقم القومي مسجل بالفعل.',
            'department.required' => 'القسم مطلوب.',
            'department.min' => 'يجب أن يتكون اسم القسم من حرفين على الأقل.',
            'section_number.required' => 'رقم السكشن مطلوب.',
            'section_number.integer' => 'رقم السكشن يجب أن يكون رقمًا.',
            'section_number.min' => 'رقم السكشن يجب أن يكون من 1 إلى 999.',
            'section_number.max' => 'رقم السكشن يجب أن يكون من 1 إلى 999.',
            'student_group.required' => 'يجب اختيار المجموعة.',
            'student_group.in' => 'المجموعة يجب أن تكون A أو B.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->cleanText($this->input('full_name')),
            'department' => $this->cleanText($this->input('department')),
            'academic_number' => $this->normalizeNumber($this->input('academic_number')),
            'national_id' => $this->normalizeNumber($this->input('national_id')),
            'section_number' => $this->normalizeNumber($this->input('section_number')),
            'student_group' => $this->normalizeGroup($this->input('student_group')),
        ]);
    }

    private function cleanText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $collapsed = preg_replace('/\s+/u', ' ', trim($value));

        return is_string($collapsed) ? $collapsed : trim($value);
    }

    private function normalizeNumber(mixed $value): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        $normalized = strtr(trim((string) $value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $withoutSpaces = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        if (preg_match('/^\d+$/', $withoutSpaces) === 1) {
            return $withoutSpaces;
        }

        if (trim((string) $value) === '') {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeGroup(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $group = strtoupper(trim($value));

        return $group === '' ? '' : $group;
    }
}
