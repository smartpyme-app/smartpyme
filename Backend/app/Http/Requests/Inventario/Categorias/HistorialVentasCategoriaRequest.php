<?php

namespace App\Http\Requests\Inventario\Categorias;

use Illuminate\Foundation\Http\FormRequest;

class HistorialVentasCategoriaRequest extends FormRequest
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
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'inicio.required' => 'La fecha de inicio es requerida.',
            'inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fin.required' => 'La fecha de fin es requerida.',
            'fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}

