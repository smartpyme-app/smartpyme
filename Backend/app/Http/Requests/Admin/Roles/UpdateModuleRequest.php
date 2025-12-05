<?php

namespace App\Http\Requests\Admin\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
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
        $id = $this->route('id');
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('modules', 'name')->ignore($id),
            ],
            'display_name' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:500',
            'submodules' => 'sometimes|array',
            'submodules.*.id' => 'sometimes|nullable|integer|exists:submodules,id',
            'submodules.*.name' => 'required_with:submodules|string|max:255',
            'submodules.*.display_name' => 'required_with:submodules|string|max:255',
            'custom_permissions' => 'sometimes|array',
            'custom_permissions.*.action' => 'required_with:custom_permissions|string|max:255',
            'custom_permissions.*.applyToModule' => 'sometimes|boolean',
            'custom_permissions.*.targets' => 'sometimes|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del módulo es obligatorio.',
            'name.unique' => 'Ya existe un módulo con ese nombre.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'display_name.required' => 'El nombre para mostrar es obligatorio.',
            'display_name.max' => 'El nombre para mostrar no puede exceder 255 caracteres.',
            'description.max' => 'La descripción no puede exceder 500 caracteres.',
            'submodules.array' => 'Los submódulos deben ser un array.',
            'submodules.*.id.exists' => 'Uno o más submódulos no existen.',
            'submodules.*.name.required_with' => 'El nombre del submódulo es obligatorio.',
            'submodules.*.name.max' => 'El nombre del submódulo no puede exceder 255 caracteres.',
            'submodules.*.display_name.required_with' => 'El nombre para mostrar del submódulo es obligatorio.',
            'submodules.*.display_name.max' => 'El nombre para mostrar del submódulo no puede exceder 255 caracteres.',
            'custom_permissions.array' => 'Los permisos personalizados deben ser un array.',
            'custom_permissions.*.action.required_with' => 'La acción del permiso personalizado es obligatoria.',
            'custom_permissions.*.action.max' => 'La acción no puede exceder 255 caracteres.',
            'custom_permissions.*.applyToModule.boolean' => 'El campo applyToModule debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del módulo',
            'display_name' => 'nombre para mostrar',
            'description' => 'descripción',
            'submodules' => 'submódulos',
            'custom_permissions' => 'permisos personalizados',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        if ($this->has('display_name')) {
            $this->merge([
                'display_name' => trim($this->display_name),
            ]);
        }
    }
}

