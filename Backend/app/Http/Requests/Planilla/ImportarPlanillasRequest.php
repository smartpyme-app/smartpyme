<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class ImportarPlanillasRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date'],
            'tipo_planilla' => ['required', 'string', 'in:quincenal,mensual'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es requerido.',
            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.mimes' => 'El archivo debe ser un archivo Excel (xlsx o xls).',
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es requerida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'tipo_planilla.required' => 'El tipo de planilla es requerido.',
            'tipo_planilla.in' => 'El tipo de planilla debe ser: quincenal o mensual.',
        ];
    }
}

