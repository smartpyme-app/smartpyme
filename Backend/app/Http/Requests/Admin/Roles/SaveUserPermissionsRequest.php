<?php

namespace App\Http\Requests\Admin\Roles;

use Illuminate\Foundation\Http\FormRequest;

class SaveUserPermissionsRequest extends FormRequest
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
            'added_permissions' => 'present|array',
            'added_permissions.*' => 'string|exists:permissions,name',
            'removed_permissions' => 'present|array',
            'removed_permissions.*' => 'string|exists:permissions,name',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'added_permissions.present' => 'El campo permisos agregados debe estar presente.',
            'added_permissions.array' => 'Los permisos agregados deben ser un array.',
            'added_permissions.*.exists' => 'Uno o más permisos agregados no existen.',
            'removed_permissions.present' => 'El campo permisos removidos debe estar presente.',
            'removed_permissions.array' => 'Los permisos removidos deben ser un array.',
            'removed_permissions.*.exists' => 'Uno o más permisos removidos no existen.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'added_permissions' => 'permisos agregados',
            'removed_permissions' => 'permisos removidos',
        ];
    }
}

