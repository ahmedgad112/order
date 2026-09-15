<?php

namespace App\Http\Requests;

use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IssueTicketRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'order_number' => ['required', 'digits:9'],
            'request_type' => ['required', 'string', Rule::in($this->allowedTypeValues())],
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
            'order_number.required' => 'رقم الطلب مطلوب.',
            'order_number.digits' => 'يجب أن يتكون رقم الطلب من 9 أرقام بالضبط.',
            'request_type.required' => 'يجب اختيار نوع الطلب.',
            'request_type.in' => 'نوع الطلب غير متاح حالياً.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('full_name') && is_string($this->full_name)) {
            $this->merge([
                'full_name' => trim($this->full_name),
            ]);
        }

        if ($this->exists('order_number') && is_string($this->order_number)) {
            $this->merge([
                'order_number' => trim($this->order_number),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $orderNumber = $this->string('order_number')->toString();

            $hasDuplicate = QueueTicket::query()
                ->today()
                ->active()
                ->where('order_number', $orderNumber)
                ->exists();

            if ($hasDuplicate) {
                $validator->errors()->add(
                    'order_number',
                    'يوجد تذكرة نشطة اليوم بنفس رقم الطلب.',
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function allowedTypeValues(): array
    {
        $lanes = QueueSystemSetting::current()->enabledQueueLaneValues();
        $user = $this->user();

        if ($user instanceof User && $user->constrainsTicketsToAssignedLanes()) {
            return array_values(array_intersect($lanes, $user->queueLaneValues()));
        }

        return $lanes;
    }
}
