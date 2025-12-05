<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracionPlanillaRequest extends FormRequest
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
            'configuracion' => ['required', 'array'],
            'configuracion.conceptos' => ['required', 'array'],
            'cod_pais' => ['sometimes', 'string', 'max:3'],
            'fecha_vigencia_desde' => ['sometimes', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'configuracion.required' => 'La configuración es requerida.',
            'configuracion.array' => 'La configuración debe ser un arreglo.',
            'configuracion.conceptos.required' => 'Los conceptos son requeridos.',
            'configuracion.conceptos.array' => 'Los conceptos deben ser un arreglo.',
            'cod_pais.max' => 'El código de país no puede exceder 3 caracteres.',
            'fecha_vigencia_desde.date' => 'La fecha de vigencia debe ser una fecha válida.',
        ];
    }
}

