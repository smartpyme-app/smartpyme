<?php

namespace App\Http\Requests\Contabilidad\Partidas;

use Illuminate\Foundation\Http\FormRequest;

class CerrarPartidasRequest extends FormRequest
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
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'month.required' => 'El mes es requerido.',
            'month.integer' => 'El mes debe ser un número entero.',
            'month.min' => 'El mes debe ser mayor o igual a 1.',
            'month.max' => 'El mes debe ser menor o igual a 12.',
            'year.required' => 'El año es requerido.',
            'year.integer' => 'El año debe ser un número entero.',
            'year.min' => 'El año debe ser mayor o igual a 2020.',
            'year.max' => 'El año debe ser menor o igual a 2030.',
        ];
    }
}

