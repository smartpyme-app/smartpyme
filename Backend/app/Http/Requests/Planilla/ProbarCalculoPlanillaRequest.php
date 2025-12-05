<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class ProbarCalculoPlanillaRequest extends FormRequest
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
            'salario_base' => ['required', 'numeric', 'min:0'],
            'dias_laborados' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'tipo_planilla' => ['sometimes', 'string', 'in:mensual,quincenal,semanal'],
            'tipo_contrato' => ['sometimes', 'integer'],
            'horas_extra' => ['sometimes', 'numeric', 'min:0'],
            'monto_horas_extra' => ['sometimes', 'numeric', 'min:0'],
            'comisiones' => ['sometimes', 'numeric', 'min:0'],
            'bonificaciones' => ['sometimes', 'numeric', 'min:0'],
            'otros_ingresos' => ['sometimes', 'numeric', 'min:0'],
            'prestamos' => ['sometimes', 'numeric', 'min:0'],
            'anticipos' => ['sometimes', 'numeric', 'min:0'],
            'otros_descuentos' => ['sometimes', 'numeric', 'min:0'],
            'descuentos_judiciales' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'salario_base.required' => 'El salario base es requerido.',
            'salario_base.numeric' => 'El salario base debe ser un número.',
            'salario_base.min' => 'El salario base no puede ser negativo.',
            'dias_laborados.integer' => 'Los días laborados deben ser un número entero.',
            'dias_laborados.min' => 'Los días laborados deben ser al menos 1.',
            'dias_laborados.max' => 'Los días laborados no pueden exceder 31.',
            'tipo_planilla.in' => 'El tipo de planilla debe ser: mensual, quincenal o semanal.',
            'tipo_contrato.integer' => 'El tipo de contrato debe ser un número entero.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        $numericFields = [
            'salario_base', 'horas_extra', 'monto_horas_extra', 'comisiones',
            'bonificaciones', 'otros_ingresos', 'prestamos', 'anticipos',
            'otros_descuentos', 'descuentos_judiciales'
        ];

        foreach ($numericFields as $field) {
            if ($this->has($field) && $this->$field !== null) {
                $this->merge([$field => (float) $this->$field]);
            }
        }
    }
}

