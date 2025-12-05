<?php

namespace App\Http\Requests\Admin\ReporteConfiguracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEstadoReporteConfiguracionRequest extends FormRequest
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
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'activo.required' => 'El estado activo es requerido.',
            'activo.boolean' => 'El estado activo debe ser un booleano.',
        ];
    }
}

