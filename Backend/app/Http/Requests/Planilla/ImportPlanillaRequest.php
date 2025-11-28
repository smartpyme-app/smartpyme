<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class ImportPlanillaRequest extends FormRequest
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
            'archivo' => 'required|file|mimes:xlsx,xls',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo_planilla' => 'required|in:quincenal,mensual'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es requerido',
            'archivo.file' => 'Debe ser un archivo válido',
            'archivo.mimes' => 'El archivo debe ser de tipo Excel (xlsx, xls)',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.required' => 'La fecha de fin es requerida',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'tipo_planilla.required' => 'El tipo de planilla es requerido',
            'tipo_planilla.in' => 'El tipo de planilla debe ser: quincenal o mensual'
        ];
    }
}

