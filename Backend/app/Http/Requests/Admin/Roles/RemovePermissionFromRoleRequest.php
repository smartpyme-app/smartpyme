<?php

namespace App\Http\Requests\Admin\Roles;

use Illuminate\Foundation\Http\FormRequest;

class RemovePermissionFromRoleRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'role' => 'required|string|exists:roles,name',
            'permission' => 'required|string|exists:permissions,name',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'role.required' => 'El rol es obligatorio.',
            'role.exists' => 'El rol seleccionado no existe.',
            'permission.required' => 'El permiso es obligatorio.',
            'permission.exists' => 'El permiso seleccionado no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'role' => 'rol',
            'permission' => 'permiso',
        ];
    }
}

