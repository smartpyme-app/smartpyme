<?php

namespace App\Http\Requests\Admin\Roles;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
            'is_global' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'permissions.present' => 'El campo permisos debe estar presente.',
            'permissions.array' => 'Los permisos deben ser un array.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
            'is_global.boolean' => 'El campo is_global debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del rol',
            'permissions' => 'permisos',
            'is_global' => 'rol global',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        // Convertir is_global a boolean
        if ($this->has('is_global')) {
            $this->merge([
                'is_global' => filter_var($this->is_global, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}

