<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanillaDetalleRequest extends FormRequest
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
            'horas_extra' => 'nullable|numeric|min:0',
            'monto_horas_extra' => 'nullable|numeric|min:0',
            'detalle_horas_extra' => 'nullable|array',
            'detalle_horas_extra.diurna' => 'nullable|numeric|min:0',
            'detalle_horas_extra.nocturna' => 'nullable|numeric|min:0',
            'detalle_horas_extra.dia_descanso' => 'nullable|numeric|min:0',
            'detalle_horas_extra.dia_descanso_dias' => 'nullable|integer|min:0',
            'detalle_horas_extra.dia_asueto' => 'nullable|numeric|min:0',
            'comisiones' => 'nullable|numeric|min:0',
            'bonificaciones' => 'nullable|numeric|min:0',
            'otros_ingresos' => 'nullable|numeric|min:0',
            'viaticos' => 'nullable|numeric|min:0',
            'abonos' => 'nullable|numeric|min:0',
            'abonos_sin_retencion' => 'nullable|boolean',
            'dias_laborados' => 'nullable|numeric|min:0|max:31',
            'prestamos' => 'nullable|numeric|min:0',
            'anticipos' => 'nullable|numeric|min:0',
            'otros_descuentos' => 'nullable|numeric|min:0',
            'descuentos_judiciales' => 'nullable|numeric|min:0',
            'detalle_otras_deducciones' => 'nullable|string',
            'salario_base' => 'nullable|numeric|min:0',
            'abonos_prestamos' => 'nullable|array',
            'abonos_prestamos.*.id_prestamo' => 'required_with:abonos_prestamos|integer|exists:prestamos_empleados,id',
            'abonos_prestamos.*.monto' => 'required_with:abonos_prestamos|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'horas_extra.numeric' => 'Las horas extra deben ser un número',
            'horas_extra.min' => 'Las horas extra no pueden ser negativas',
            'comisiones.numeric' => 'Las comisiones deben ser un número',
            'comisiones.min' => 'Las comisiones no pueden ser negativas',
            'bonificaciones.numeric' => 'Las bonificaciones deben ser un número',
            'bonificaciones.min' => 'Las bonificaciones no pueden ser negativas',
            'dias_laborados.numeric' => 'Los días laborados deben ser un número',
            'dias_laborados.min' => 'Los días laborados no pueden ser negativos',
            'dias_laborados.max' => 'Los días laborados no pueden ser mayores a 31'
        ];
    }
}

