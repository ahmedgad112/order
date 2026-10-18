<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupStudentRegistrationRequest extends FormRequest
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
            'query' => ['required', 'regex:/^\d{4,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'query.required' => 'اكتب الرقم الأكاديمي أو الرقم القومي.',
            'query.regex' => 'اكتب الرقم الأكاديمي أو الرقم القومي بشكل صحيح.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'query' => $this->normalizeNumber($this->input('query')),
        ]);
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
}
