<?php

namespace App\Http\Requests\Inventario\MateriaPrima;

use Illuminate\Foundation\Http\FormRequest;

class StoreMateriaPrimaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:productos,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La materia prima seleccionada no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'empresa_id.required' => 'La empresa es requerida.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

