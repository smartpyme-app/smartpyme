<?php

namespace App\Http\Requests\MH;

use Illuminate\Foundation\Http\FormRequest;

class GenerarContingenciaRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:ventas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la venta es requerido.',
            'id.integer' => 'El ID de la venta debe ser un número entero.',
            'id.exists' => 'La venta seleccionada no existe.',
        ];
    }
}

