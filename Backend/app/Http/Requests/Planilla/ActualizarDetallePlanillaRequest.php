<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarDetallePlanillaRequest extends FormRequest
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
            'salario_devengado' => ['sometimes', 'numeric', 'min:0'],
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
            'salario_devengado.numeric' => 'El salario devengado debe ser un número.',
            'salario_devengado.min' => 'El salario devengado no puede ser negativo.',
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
            'prestamos.numeric' => 'Los préstamos deben ser un número.',
            'prestamos.min' => 'Los préstamos no pueden ser negativos.',
            'anticipos.numeric' => 'Los anticipos deben ser un número.',
            'anticipos.min' => 'Los anticipos no pueden ser negativos.',
            'otros_descuentos.numeric' => 'Los otros descuentos deben ser un número.',
            'otros_descuentos.min' => 'Los otros descuentos no pueden ser negativos.',
            'descuentos_judiciales.numeric' => 'Los descuentos judiciales deben ser un número.',
            'descuentos_judiciales.min' => 'Los descuentos judiciales no pueden ser negativos.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        $numericFields = [
            'salario_devengado', 'horas_extra', 'monto_horas_extra', 'comisiones',
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

