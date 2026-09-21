<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQueueLaneTellersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $queueRoleSlugs = Role::catalog()
            ->where('serves_queue', true)
            ->pluck('slug')
            ->all();

        return [
            'teller_ids' => ['present', 'array'],
            'teller_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->whereIn('role', $queueRoleSlugs !== [] ? $queueRoleSlugs : ['__none__']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teller_ids.present' => 'يجب تحديد الموظفين المخصصين.',
            'teller_ids.array' => 'قائمة الموظفين غير صحيحة.',
            'teller_ids.*.exists' => 'أحد الموظفين المحددين غير موجود أو ليس موظفاً.',
        ];
    }
}
