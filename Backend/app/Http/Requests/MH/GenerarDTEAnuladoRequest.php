<?php

namespace App\Http\Requests\MH;

use Illuminate\Foundation\Http\FormRequest;

class GenerarDTEAnuladoRequest extends FormRequest
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
            'id' => ['required', 'integer'],
            'tipo_dte' => ['required', 'string', 'in:01,03,05,06,11'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del registro es requerido.',
            'id.integer' => 'El ID del registro debe ser un número entero.',
            'tipo_dte.required' => 'El tipo de DTE es requerido.',
            'tipo_dte.in' => 'El tipo de DTE debe ser uno de: 01, 03, 05, 06, 11.',
        ];
    }
}

