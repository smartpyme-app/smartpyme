<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class StoreHistorialContratoRequest extends FormRequest
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
            'id_empleado' => ['required', 'integer', 'exists:empleados,id'],
            'fecha_inicio' => ['required', 'date'],
            'tipo_contrato' => ['required', 'string'],
            'salario' => ['required', 'numeric', 'min:0'],
            'id_cargo' => ['required', 'integer', 'exists:cargos_de_empresa,id'],
            'motivo_cambio' => ['required', 'string'],
            'tipo_jornada' => ['nullable', 'integer'],
            'fecha_fin' => ['nullable', 'date'],
            'estado' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_empleado.required' => 'El empleado es requerido.',
            'id_empleado.exists' => 'El empleado seleccionado no existe.',
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'tipo_contrato.required' => 'El tipo de contrato es requerido.',
            'salario.required' => 'El salario es requerido.',
            'salario.numeric' => 'El salario debe ser un número.',
            'salario.min' => 'El salario no puede ser negativo.',
            'id_cargo.required' => 'El cargo es requerido.',
            'id_cargo.exists' => 'El cargo seleccionado no existe.',
            'motivo_cambio.required' => 'El motivo del cambio es requerido.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('salario')) {
            $this->merge(['salario' => (float) $this->salario]);
        }

        // Sanitizar motivo_cambio
        if ($this->has('motivo_cambio')) {
            $this->merge(['motivo_cambio' => trim($this->motivo_cambio)]);
        }
    }
}

