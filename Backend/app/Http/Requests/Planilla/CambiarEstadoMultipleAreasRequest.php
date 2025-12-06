<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoMultipleAreasRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:areas_empresa,id'],
            'activo' => ['required', 'in:0,1,true,false'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Los IDs son requeridos.',
            'ids.array' => 'Los IDs deben ser un arreglo.',
            'ids.min' => 'Debe haber al menos un ID.',
            'ids.*.required' => 'Cada ID es requerido.',
            'ids.*.integer' => 'Cada ID debe ser un número entero.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
            'activo.required' => 'El campo activo es requerido.',
            'activo.in' => 'El campo activo debe ser: 0, 1, true o false.',
        ];
    }
}

