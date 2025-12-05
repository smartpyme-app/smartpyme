<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class RetaceoRequest extends FormRequest
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
            'id_retaceo' => ['required', 'integer', 'exists:retaceos,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_retaceo.required' => 'El ID del retaceo es requerido.',
            'id_retaceo.integer' => 'El ID del retaceo debe ser un número entero.',
            'id_retaceo.exists' => 'El retaceo seleccionado no existe.',
        ];
    }
}

