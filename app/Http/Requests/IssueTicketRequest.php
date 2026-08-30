<?php

namespace App\Http\Requests;

use App\Models\QueueTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IssueTicketRequest extends FormRequest
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
            'national_id' => ['required', 'digits:14'],
            'order_number' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب.',
            'full_name.min' => 'يجب أن يتكون الاسم من 3 أحرف على الأقل.',
            'national_id.required' => 'الرقم القومي مطلوب.',
            'national_id.digits' => 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.',
            'order_number.required' => 'رقم الطلب مطلوب.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $nationalId = $this->string('national_id')->toString();
            $orderNumber = $this->string('order_number')->toString();

            $hasDuplicate = QueueTicket::query()
                ->today()
                ->active()
                ->where(function ($query) use ($nationalId, $orderNumber): void {
                    $query->where('national_id', $nationalId)
                        ->orWhere('order_number', $orderNumber);
                })
                ->exists();

            if ($hasDuplicate) {
                $validator->errors()->add(
                    'national_id',
                    'يوجد تذكرة نشطة اليوم بنفس الرقم القومي أو رقم الطلب.'
                );
            }
        });
    }
}
