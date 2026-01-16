<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \Spatie\Permission\Models\Role $role */
        $role = $this->route('role');
        $roleId = $role->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$roleId],
            'permission_ids' => ['sometimes', 'nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
        ];
    }
}
