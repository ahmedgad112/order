<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageRoles() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('roles', 'slug')],
            'rank' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'serves_queue' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*.permission' => ['required_with:permissions', 'string', Rule::enum(Permission::class)],
            'permissions.*.allowed' => ['required_with:permissions', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدور مطلوب.',
            'name.min' => 'اسم الدور قصير جداً.',
            'slug.unique' => 'معرّف الدور مستخدم بالفعل.',
            'slug.alpha_dash' => 'معرّف الدور يجب أن يحتوي حروفاً إنجليزية وأرقاماً وشرطة فقط.',
        ];
    }
}
