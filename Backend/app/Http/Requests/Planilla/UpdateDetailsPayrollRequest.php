<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDetailsPayrollRequest extends FormRequest
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
            'horas_extra' => ['nullable', 'numeric', 'min:0'],
            'monto_horas_extra' => ['nullable', 'numeric', 'min:0'],
            'comisiones' => ['nullable', 'numeric', 'min:0'],
            'bonificaciones' => ['nullable', 'numeric', 'min:0'],
            'otros_ingresos' => ['nullable', 'numeric', 'min:0'],
            'dias_laborados' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'prestamos' => ['nullable', 'numeric', 'min:0'],
            'anticipos' => ['nullable', 'numeric', 'min:0'],
            'otros_descuentos' => ['nullable', 'numeric', 'min:0'],
            'descuentos_judiciales' => ['nullable', 'numeric', 'min:0'],
            'detalle_otras_deducciones' => ['nullable', 'string'],
            'salario_base' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'horas_extra.numeric' => 'Las horas extra deben ser un número.',
            'horas_extra.min' => 'Las horas extra no pueden ser negativas.',
            'monto_horas_extra.numeric' => 'El monto de horas extra debe ser un número.',
            'monto_horas_extra.min' => 'El monto de horas extra no puede ser negativo.',
            'comisiones.numeric' => 'Las comisiones deben ser un número.',
            'comisiones.min' => 'Las comisiones no pueden ser negativas.',
            'bonificaciones.numeric' => 'Las bonificaciones deben ser un número.',
            'bonificaciones.min' => 'Las bonificaciones no pueden ser negativas.',
            'otros_ingresos.numeric' => 'Los otros ingresos deben ser un número.',
            'otros_ingresos.min' => 'Los otros ingresos no pueden ser negativos.',
            'dias_laborados.numeric' => 'Los días laborados deben ser un número.',
            'dias_laborados.min' => 'Los días laborados no pueden ser negativos.',
            'dias_laborados.max' => 'Los días laborados no pueden exceder 31.',
            'prestamos.numeric' => 'Los préstamos deben ser un número.',
            'prestamos.min' => 'Los préstamos no pueden ser negativos.',
            'anticipos.numeric' => 'Los anticipos deben ser un número.',
            'anticipos.min' => 'Los anticipos no pueden ser negativos.',
            'otros_descuentos.numeric' => 'Los otros descuentos deben ser un número.',
            'otros_descuentos.min' => 'Los otros descuentos no pueden ser negativos.',
            'descuentos_judiciales.numeric' => 'Los descuentos judiciales deben ser un número.',
            'descuentos_judiciales.min' => 'Los descuentos judiciales no pueden ser negativos.',
            'salario_base.numeric' => 'El salario base debe ser un número.',
            'salario_base.min' => 'El salario base no puede ser negativo.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        $numericFields = [
            'horas_extra', 'monto_horas_extra', 'comisiones', 'bonificaciones',
            'otros_ingresos', 'dias_laborados', 'prestamos', 'anticipos',
            'otros_descuentos', 'descuentos_judiciales', 'salario_base'
        ];

        foreach ($numericFields as $field) {
            if ($this->has($field) && $this->$field !== null) {
                $this->merge([$field => (float) $this->$field]);
            }
        }
    }
}

