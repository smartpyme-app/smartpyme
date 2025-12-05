<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class ValidarCalculoRentaRequest extends FormRequest
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
            'salario_devengado' => ['required', 'numeric', 'min:0'],
            'isss_empleado' => ['required', 'numeric', 'min:0'],
            'afp_empleado' => ['required', 'numeric', 'min:0'],
            'tipo_planilla' => ['required', 'string', 'in:mensual,quincenal,semanal'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'salario_devengado.required' => 'El salario devengado es requerido.',
            'salario_devengado.numeric' => 'El salario devengado debe ser un número.',
            'salario_devengado.min' => 'El salario devengado no puede ser negativo.',
            'isss_empleado.required' => 'El ISSS del empleado es requerido.',
            'isss_empleado.numeric' => 'El ISSS del empleado debe ser un número.',
            'isss_empleado.min' => 'El ISSS del empleado no puede ser negativo.',
            'afp_empleado.required' => 'El AFP del empleado es requerido.',
            'afp_empleado.numeric' => 'El AFP del empleado debe ser un número.',
            'afp_empleado.min' => 'El AFP del empleado no puede ser negativo.',
            'tipo_planilla.required' => 'El tipo de planilla es requerido.',
            'tipo_planilla.in' => 'El tipo de planilla debe ser: mensual, quincenal o semanal.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('salario_devengado')) {
            $this->merge(['salario_devengado' => (float) $this->salario_devengado]);
        }

        if ($this->has('isss_empleado')) {
            $this->merge(['isss_empleado' => (float) $this->isss_empleado]);
        }

        if ($this->has('afp_empleado')) {
            $this->merge(['afp_empleado' => (float) $this->afp_empleado]);
        }
    }
}
