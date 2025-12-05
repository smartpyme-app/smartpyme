<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarDetallesPlanillaRequest extends FormRequest
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
            'id_planilla' => ['required', 'integer', 'exists:planillas,id'],
            'vista' => ['nullable', 'string', Rule::in(['empleados', 'resumen', 'detallado'])],
            'buscador' => ['nullable', 'string', 'max:255'],
            'id_departamento' => ['nullable', 'integer', 'exists:departamentos_empresa,id'],
            'id_cargo' => ['nullable', 'integer', 'exists:cargos_de_empresa,id'],
            'estado' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_planilla.required' => 'El ID de la planilla es requerido.',
            'id_planilla.integer' => 'El ID de la planilla debe ser un número entero.',
            'id_planilla.exists' => 'La planilla seleccionada no existe.',
            'vista.in' => 'La vista debe ser: empleados, resumen o detallado.',
            'buscador.max' => 'El buscador no puede exceder 255 caracteres.',
            'id_departamento.integer' => 'El ID del departamento debe ser un número entero.',
            'id_departamento.exists' => 'El departamento seleccionado no existe.',
            'id_cargo.integer' => 'El ID del cargo debe ser un número entero.',
            'id_cargo.exists' => 'El cargo seleccionado no existe.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
        ];
    }
}

