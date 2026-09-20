<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.role' => ['required', 'string', Rule::enum(UserRole::class)],
            'permissions.*.permission' => ['required', 'string', Rule::enum(Permission::class)],
            'permissions.*.allowed' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.required' => 'يجب إرسال قائمة الصلاحيات.',
            'permissions.*.role.required' => 'الدور مطلوب.',
            'permissions.*.permission.required' => 'الصلاحية مطلوبة.',
            'permissions.*.allowed.required' => 'حالة الصلاحية مطلوبة.',
        ];
    }
}
