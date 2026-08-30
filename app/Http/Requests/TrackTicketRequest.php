<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackTicketRequest extends FormRequest
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
            'national_id' => ['nullable', 'required_without:order_number', 'digits:14'],
            'order_number' => ['nullable', 'required_without:national_id', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'national_id.required_without' => 'أدخل الرقم القومي أو رقم الطلب.',
            'national_id.digits' => 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.',
            'order_number.required_without' => 'أدخل الرقم القومي أو رقم الطلب.',
        ];
    }
}
