<?php

namespace App\Http\Requests\Admin\Suscripciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSuscriptionRequest extends FormRequest
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
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'plan_id' => ['required', 'integer', 'exists:planes,id'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'tipo_plan' => ['required', 'string', Rule::in(['Mensual', 'Anual'])],
            'estado' => ['required', 'string', Rule::in(['Activo', 'Cancelado', 'Vencido', 'En prueba', 'Pendiente'])],
            'monto' => ['required', 'numeric', 'min:0'],
            'fecha_proximo_pago' => ['required', 'date'],
            'fin_periodo_prueba' => ['required', 'date'],
            'nit' => ['nullable', 'string', 'max:20'],
            'nombre_factura' => ['nullable', 'string', 'max:255'],
            'direccion_factura' => ['nullable', 'string', 'max:500'],
            'requiere_factura' => ['nullable', 'boolean'],
            'motivo_cancelacion' => ['nullable', 'string', 'max:500'],
            'estado_ultimo_pago' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'empresa_id.required' => 'La empresa es requerida.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'plan_id.required' => 'El plan es requerido.',
            'plan_id.exists' => 'El plan seleccionado no existe.',
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
            'tipo_plan.required' => 'El tipo de plan es requerido.',
            'tipo_plan.in' => 'El tipo de plan debe ser Mensual o Anual.',
            'estado.required' => 'El estado es requerido.',
            'estado.in' => 'El estado no es válido.',
            'monto.required' => 'El monto es requerido.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto no puede ser negativo.',
            'fecha_proximo_pago.required' => 'La fecha de próximo pago es requerida.',
            'fecha_proximo_pago.date' => 'La fecha de próximo pago debe ser una fecha válida.',
            'fin_periodo_prueba.required' => 'La fecha de fin de período de prueba es requerida.',
            'fin_periodo_prueba.date' => 'La fecha de fin de período de prueba debe ser una fecha válida.',
            'nit.max' => 'El NIT no puede exceder 20 caracteres.',
            'nombre_factura.max' => 'El nombre de factura no puede exceder 255 caracteres.',
            'direccion_factura.max' => 'La dirección de factura no puede exceder 500 caracteres.',
            'motivo_cancelacion.max' => 'El motivo de cancelación no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->estado === 'Cancelado' && empty($this->motivo_cancelacion)) {
                $validator->errors()->add('motivo_cancelacion', 'El motivo de cancelación es requerido cuando el estado es Cancelado.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nit')) {
            $this->merge(['nit' => trim($this->nit)]);
        }

        if ($this->has('nombre_factura')) {
            $this->merge(['nombre_factura' => trim($this->nombre_factura)]);
        }

        if ($this->has('direccion_factura')) {
            $this->merge(['direccion_factura' => trim($this->direccion_factura)]);
        }

        if ($this->has('motivo_cancelacion')) {
            $this->merge(['motivo_cancelacion' => trim($this->motivo_cancelacion)]);
        }

        // Convertir valores numéricos
        if ($this->has('monto')) {
            $this->merge(['monto' => (float) $this->monto]);
        }
    }
}

