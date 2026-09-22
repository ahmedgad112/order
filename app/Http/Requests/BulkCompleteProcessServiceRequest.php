<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class BulkCompleteProcessServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['required', 'integer', 'distinct', 'exists:queue_tickets,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_ids.required' => 'يجب اختيار تذكرة واحدة على الأقل.',
            'ticket_ids.min' => 'يجب اختيار تذكرة واحدة على الأقل.',
            'ticket_ids.max' => 'يمكن تطبيق العملية على 100 تذكرة كحد أقصى.',
            'ticket_ids.*.exists' => 'إحدى التذاكر غير موجودة.',
        ];
    }
}
