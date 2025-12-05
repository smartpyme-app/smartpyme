<?php

namespace App\Http\Requests\Admin\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInfoRequest extends FormRequest
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
        $id = $this->input('id');
        
        return [
            'id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'telefono' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('users', 'telefono')->ignore($id),
            ],
            'tipo' => 'required|string',
            'codigo' => 'sometimes|nullable|string|max:255',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del usuario es obligatorio.',
            'id.exists' => 'El usuario no existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'telefono.unique' => 'Este teléfono ya está registrado.',
            'tipo.required' => 'El tipo de usuario es obligatorio.',
            'codigo.max' => 'El código no puede exceder 255 caracteres.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'usuario',
            'name' => 'nombre',
            'telefono' => 'teléfono',
            'tipo' => 'tipo de usuario',
            'codigo' => 'código',
            'id_sucursal' => 'sucursal',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar teléfono (remover caracteres no numéricos)
        if ($this->has('telefono') && $this->telefono) {
            $this->merge([
                'telefono' => preg_replace('/[^0-9]/', '', $this->telefono),
            ]);
        }

        // Trim de strings
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        if ($this->has('codigo') && $this->codigo) {
            $this->merge([
                'codigo' => trim($this->codigo),
            ]);
        }
    }
}

