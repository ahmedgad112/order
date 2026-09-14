<?php

namespace App\Http\Requests;

use App\Enums\ProcessStep;
use App\Enums\RequestType;
use App\Enums\StudentKind;
use App\Models\College;
use App\Models\Faculty;
use App\Models\QueueTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEditTickets() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isCurrentStudentTicket()) {
            return [
                'full_name' => ['required', 'string', 'min:3', 'max:255'],
                'college' => ['required', 'string', Rule::in($this->allowedFacultySlugs())],
                'department' => ['required', 'string', 'min:2', 'max:255'],
                'seat_number' => ['required', ...Faculty::seatNumberRulesFor($this->input('college'))],
            ];
        }

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'national_id' => ['required', 'digits:14'],
            'request_type' => ['required', Rule::enum(RequestType::class)],
            'completion_step' => [
                'exclude_unless:request_type,'.RequestType::DocumentCompletion->value,
                'required',
                Rule::in(ProcessStep::admissionCompletionValues()),
            ],
            'college' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::when(
                    $this->input('request_type') === RequestType::NominationCard->value,
                    [Rule::in($this->allowedCollegeSlugs())],
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
            'request_type.required' => 'يجب اختيار نوع الطلب.',
            'request_type.enum' => 'نوع الطلب غير صحيح.',
            'completion_step.required' => 'يجب اختيار الخدمة المراد استكمال أوراقها.',
            'completion_step.in' => 'الخدمة المراد استكمال أوراقها غير صحيحة.',
            'college.required' => 'يجب تحديد الكلية.',
            'college.min' => 'يجب كتابة اسم الكلية.',
            'college.in' => $this->isCurrentStudentTicket()
                ? 'يجب اختيار الكلية الصحيحة.'
                : 'يجب اختيار الكلية الواردة في بطاقة الترشيح.',
            'order_number.required' => 'رقم الطلب مطلوب.',
            'order_number.digits' => 'يجب أن يتكون رقم الطلب من 9 أرقام بالضبط.',
            'department.required' => 'يجب كتابة القسم.',
            'department.min' => 'يجب كتابة اسم القسم.',
            'seat_number.required' => 'رقم الجلوس مطلوب.',
            'seat_number.digits' => 'يجب أن يتكون رقم الجلوس من :digits أرقام بالضبط.',
            'seat_number.digits_between' => 'يجب أن يتكون رقم الجلوس من :min إلى :max أرقام.',
        ];
    }

    protected function prepareForValidation(): void
    {
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
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ticket = $this->route('ticket');

            if (! $ticket instanceof QueueTicket) {
                return;
            }

            if ($this->isCurrentStudentTicket()) {
                $seatNumber = $this->string('seat_number')->toString();

                $hasDuplicate = QueueTicket::query()
                    ->where('id', '!=', $ticket->id)
                    ->when(
                        $ticket->session_started_at,
                        fn ($query) => $query->where('session_started_at', $ticket->session_started_at),
                        fn ($query) => $query->onDate($ticket->created_at ?? today()),
                    )
                    ->active()
                    ->where('seat_number', $seatNumber)
                    ->exists();

                if ($hasDuplicate) {
                    $validator->errors()->add(
                        'seat_number',
                        'يوجد تذكرة نشطة بنفس رقم الجلوس.',
                    );
                }

                return;
            }

            $nationalId = $this->string('national_id')->toString();
            $orderNumber = $this->string('order_number')->toString();

            $hasDuplicate = QueueTicket::query()
                ->where('id', '!=', $ticket->id)
                ->when(
                    $ticket->session_started_at,
                    fn ($query) => $query->where('session_started_at', $ticket->session_started_at),
                    fn ($query) => $query->onDate($ticket->created_at ?? today()),
                )
                ->active()
                ->where(function ($query) use ($nationalId, $orderNumber): void {
                    $query->where('national_id', $nationalId)
                        ->orWhere('order_number', $orderNumber);
                })
                ->exists();

            if ($hasDuplicate) {
                $validator->errors()->add(
                    'national_id',
                    'يوجد تذكرة نشطة بنفس الرقم القومي أو رقم الطلب.',
                );
            }
        });
    }

    private function isCurrentStudentTicket(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof QueueTicket
            && $ticket->studentKindValue() === StudentKind::CurrentStudent->value;
    }

    /**
     * @return list<string>
     */
    private function allowedFacultySlugs(): array
    {
        return $this->allowedCatalogSlugs(Faculty::activeSlugs());
    }

    /**
     * @return list<string>
     */
    private function allowedCollegeSlugs(): array
    {
        return $this->allowedCatalogSlugs(College::activeSlugs());
    }

    /**
     * @param  list<string>  $activeSlugs
     * @return list<string>
     */
    private function allowedCatalogSlugs(array $activeSlugs): array
    {
        $ticket = $this->route('ticket');

        if ($ticket instanceof QueueTicket && filled($ticket->college)) {
            $activeSlugs[] = $ticket->college;
        }

        return array_values(array_unique($activeSlugs));
    }
}
