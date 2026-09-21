<?php

namespace App\Http\Requests;

use App\Models\Faculty;
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
            'full_name' => ['nullable', 'string', 'min:3', 'max:255'],
            'order_number' => ['nullable', 'digits:9'],
            'request_type' => ['required', 'string', Rule::in($this->allowedTypeValues())],
            'college' => ['required', 'string', Rule::in($this->allowedFacultyValues())],
            'count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.min' => 'يجب أن يتكون الاسم من 3 أحرف على الأقل.',
            'order_number.digits' => 'يجب أن يتكون رقم الطلب من 9 أرقام بالضبط.',
            'request_type.required' => 'يجب اختيار نوع الطلب.',
            'request_type.in' => 'نوع الطلب غير متاح حالياً.',
            'college.required' => 'يجب اختيار الكلية.',
            'college.in' => 'الكلية غير متاحة لحسابك.',
            'count.integer' => 'عدد الأدوار يجب أن يكون رقماً.',
            'count.min' => 'يجب إصدار دور واحد على الأقل.',
            'count.max' => 'يمكن إصدار 50 دور كحد أقصى في المرة الواحدة.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('full_name') && is_string($this->full_name)) {
            $trimmedName = trim($this->full_name);

            $this->merge([
                'full_name' => $trimmedName === '' ? null : $trimmedName,
            ]);
        }

        if ($this->exists('order_number') && is_string($this->order_number)) {
            $trimmedOrderNumber = trim($this->order_number);

            $this->merge([
                'order_number' => $trimmedOrderNumber === '' ? null : $trimmedOrderNumber,
            ]);
        }

        if ($this->exists('college') && is_string($this->college)) {
            $trimmedCollege = trim($this->college);

            $this->merge([
                'college' => $trimmedCollege === '' ? null : $trimmedCollege,
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $count = (int) ($this->input('count') ?? 1);

            if ($count > 1 && (filled($this->input('full_name')) || filled($this->input('order_number')))) {
                $validator->errors()->add(
                    'count',
                    'يمكن إصدار أكثر من دور فقط من غير اسم ورقم طلب.',
                );

                return;
            }

            $orderNumber = $this->input('order_number');

            if (! filled($orderNumber)) {
                return;
            }

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

    /**
     * @return list<string>
     */
    private function allowedFacultyValues(): array
    {
        $user = $this->user();

        if ($user instanceof User && $user->constrainsTicketsToAssignedFaculties()) {
            return $user->assignedFacultyValues();
        }

        return Faculty::slugs();
    }
}
