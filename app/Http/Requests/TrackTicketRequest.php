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
            'national_id' => ['nullable', 'required_without_all:order_number,seat_number', 'digits:14'],
            'order_number' => ['nullable', 'required_without_all:national_id,seat_number', 'string', 'max:100'],
            'seat_number' => ['nullable', 'required_without_all:national_id,order_number', 'digits_between:7,9'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'national_id.required_without_all' => 'أدخل الرقم القومي أو رقم الطلب أو رقم الجلوس.',
            'national_id.digits' => 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.',
            'order_number.required_without_all' => 'أدخل الرقم القومي أو رقم الطلب أو رقم الجلوس.',
            'seat_number.required_without_all' => 'أدخل الرقم القومي أو رقم الطلب أو رقم الجلوس.',
            'seat_number.digits_between' => 'يجب أن يتكون رقم الجلوس من 7 إلى 9 أرقام.',
        ];
    }
}
