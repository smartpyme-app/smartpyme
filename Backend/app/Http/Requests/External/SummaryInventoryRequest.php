<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class SummaryInventoryRequest extends FormRequest
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
            'categoria' => ['nullable', 'string'],
            'tipo' => ['nullable', 'string', 'in:Producto,Servicio'],
            'enable' => ['nullable', 'string', 'in:0,1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser Producto o Servicio.',
            'enable.in' => 'El estado debe ser 0 o 1.',
        ];
    }
}

