<?php

namespace App\Http\Requests\Admin\Roles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
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
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
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
            'permissions.required' => 'Los permisos son obligatorios.',
            'permissions.array' => 'Los permisos deben ser un array.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'role' => 'rol',
            'permissions' => 'permisos',
        ];
    }
}

