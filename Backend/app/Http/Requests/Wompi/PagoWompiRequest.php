<?php

namespace App\Http\Requests\Wompi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagoWompiRequest extends FormRequest
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
            'idEnlace' => ['required', 'string'],
            'idTransaccion' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'idEnlace.required' => 'El ID del enlace es requerido.',
            'idEnlace.string' => 'El ID del enlace debe ser una cadena de texto.',
            'idTransaccion.required' => 'El ID de la transacción es requerido.',
            'idTransaccion.string' => 'El ID de la transacción debe ser una cadena de texto.',
        ];
    }
}

