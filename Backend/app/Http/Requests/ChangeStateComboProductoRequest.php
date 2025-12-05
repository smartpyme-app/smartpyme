<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeStateComboProductoRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:combos_productos,id'],
            'estado' => ['required', 'string', 'in:Activo,Inactivo'],
            'id_bodega' => ['nullable', 'integer', 'exists:sucursal_bodegas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del combo es requerido.',
            'id.exists' => 'El combo seleccionado no existe.',
            'estado.required' => 'El estado es requerido.',
            'estado.in' => 'El estado debe ser Activo o Inactivo.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
        ];
    }
}

