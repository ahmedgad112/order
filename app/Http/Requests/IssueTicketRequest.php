<?php

namespace App\Http\Requests;

use App\Enums\College;
use App\Enums\DocumentKind;
use App\Enums\Faculty;
use App\Enums\ProcessStep;
use App\Enums\RequestType;
use App\Enums\StudentKind;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        if ($this->isCurrentStudent()) {
            return [
                'student_kind' => ['required', Rule::enum(StudentKind::class)],
                'full_name' => ['required', 'string', 'min:3', 'max:255'],
                'college' => ['required', 'string', Rule::in(Faculty::values())],
                'department' => ['required', 'string', 'min:2', 'max:255'],
                'seat_number' => ['required', ...Faculty::seatNumberRulesFor($this->input('college'))],
                'document_kind' => ['required', Rule::enum(DocumentKind::class)],
                'document' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ];
        }

        return [
            'student_kind' => ['required', Rule::enum(StudentKind::class)],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'national_id' => ['required', 'digits:14'],
            'request_type' => ['required', 'string', Rule::in(QueueSystemSetting::current()->enabledRequestTypeValues())],
            'completion_step' => [
                'exclude_unless:request_type,'.RequestType::DocumentCompletion->value,
                'required',
                'string',
                Rule::in(ProcessStep::admissionCompletionValues()),
            ],
            'college' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::when(
                    $this->input('request_type') === RequestType::NominationCard->value,
                    [Rule::in(College::values())],
                ),
            ],
            'order_number' => ['required', 'digits:9'],
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
            'student_kind.required' => 'يجب اختيار نوع الطالب.',
            'student_kind.enum' => 'نوع الطالب غير صحيح.',
            'request_type.required' => 'يجب اختيار نوع الطلب.',
            'request_type.in' => 'نوع الطلب غير متاح حالياً.',
            'completion_step.required' => 'يجب اختيار الخدمة المراد استكمال أوراقها.',
            'completion_step.in' => 'الخدمة المراد استكمال أوراقها غير صحيحة.',
            'college.required' => 'يجب تحديد الكلية.',
            'college.min' => 'يجب كتابة اسم الكلية.',
            'college.in' => $this->isCurrentStudent()
                ? 'يجب اختيار الكلية الصحيحة.'
                : 'يجب اختيار الكلية الواردة في بطاقة الترشيح.',
            'order_number.required' => 'رقم الطلب مطلوب.',
            'order_number.digits' => 'يجب أن يتكون رقم الطلب من 9 أرقام بالضبط.',
            'department.required' => 'يجب كتابة القسم.',
            'department.min' => 'يجب كتابة اسم القسم.',
            'seat_number.required' => 'رقم الجلوس مطلوب.',
            'seat_number.digits' => 'يجب أن يتكون رقم الجلوس من 7 أرقام بالضبط.',
            'seat_number.digits_between' => 'يجب أن يتكون رقم الجلوس من 7 إلى 9 أرقام.',
            'document_kind.required' => 'يجب اختيار نوع المستند.',
            'document_kind.enum' => 'نوع المستند غير صحيح.',
            'document.required' => 'يجب رفع صورة المستند.',
            'document.image' => 'يجب أن يكون الملف صورة.',
            'document.mimes' => 'صيغة الصورة غير مدعومة. استخدم jpg أو png أو webp.',
            'document.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('student_kind')) {
            $this->merge([
                'student_kind' => StudentKind::NewStudent->value,
            ]);
        }

        if ($this->exists('college') && is_string($this->college)) {
            $this->merge([
                'college' => trim($this->college),
            ]);
        }

        if ($this->exists('department') && is_string($this->department)) {
            $this->merge([
                'department' => trim($this->department),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->filled('student_kind')
                && StudentKind::tryFrom((string) $this->input('student_kind')) !== null
                && ! QueueSystemSetting::current()->isStudentKindEnabled($this->input('student_kind'))
            ) {
                $validator->errors()->add(
                    'student_kind',
                    $this->isCurrentStudent()
                        ? 'تقديم الطلاب الحاليين غير متاح حالياً.'
                        : 'تقديم الطلاب الجدد غير متاح حالياً.',
                );
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->isCurrentStudent()) {
                $seatNumber = $this->string('seat_number')->toString();

                $hasDuplicate = QueueTicket::query()
                    ->today()
                    ->active()
                    ->where('seat_number', $seatNumber)
                    ->exists();

                if ($hasDuplicate) {
                    $validator->errors()->add(
                        'seat_number',
                        'يوجد تذكرة نشطة اليوم بنفس رقم الجلوس.',
                    );
                }

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

    private function isCurrentStudent(): bool
    {
        return $this->input('student_kind') === StudentKind::CurrentStudent->value;
    }
}
