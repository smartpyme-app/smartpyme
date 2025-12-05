<?php

namespace App\Http\Requests\MH;

use Illuminate\Foundation\Http\FormRequest;

class GenerarDTEPDFRequest extends FormRequest
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
        // El tipo de la ruta se valida en el controlador
        // Aquí solo validamos el tipo del request cuando es necesario
        return [
            'tipo' => ['nullable', 'string', 'in:compra,gasto'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser compra o gasto.',
        ];
    }
}

