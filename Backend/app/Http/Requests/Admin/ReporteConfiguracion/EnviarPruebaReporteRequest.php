<?php

namespace App\Http\Requests\Admin\ReporteConfiguracion;

use Illuminate\Foundation\Http\FormRequest;

class EnviarPruebaReporteRequest extends FormRequest
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
            'id_configuracion' => ['required', 'integer', 'exists:reporte_configuraciones,id'],
            'email_prueba' => ['nullable', 'email'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_configuracion.required' => 'La configuración es requerida.',
            'id_configuracion.exists' => 'La configuración seleccionada no existe.',
            'email_prueba.email' => 'El correo electrónico de prueba debe ser válido.',
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es requerida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}

