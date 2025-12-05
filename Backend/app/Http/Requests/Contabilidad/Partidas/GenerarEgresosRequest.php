<?php

namespace App\Http\Requests\Contabilidad\Partidas;

use Illuminate\Foundation\Http\FormRequest;

class GenerarEgresosRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
        ];
    }
}

