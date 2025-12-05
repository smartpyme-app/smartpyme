<?php

namespace App\Http\Requests\Dash;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDashRequest extends FormRequest
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
            'inicio' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'time' => ['nullable', 'string', Rule::in(['DAY', 'WEEK', 'MONTH', 'YEAR', 'day', 'week', 'month', 'year'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'id_sucursal.integer' => 'El ID de sucursal debe ser un número entero.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'time.in' => 'El período de tiempo debe ser uno de: DAY, WEEK, MONTH, YEAR.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        if ($this->has('time')) {
            $this->merge([
                'time' => strtoupper($this->time),
            ]);
        }
    }
}

