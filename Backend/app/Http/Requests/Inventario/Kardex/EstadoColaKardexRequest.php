<?php

namespace App\Http\Requests\Inventario\Kardex;

use Illuminate\Foundation\Http\FormRequest;

class EstadoColaKardexRequest extends FormRequest
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
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_empresa.required' => 'El ID de empresa es obligatorio.',
            'id_empresa.integer' => 'El ID de empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

