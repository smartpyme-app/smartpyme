<?php

namespace App\Http\Requests\SuperAdmin\Pagos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewPaymentRequest extends FormRequest
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
            'metodo_pago' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'string', Rule::in(['Completado', 'Rechazado', 'Pendiente'])],
            'fecha_transaccion' => ['required', 'date'],
            'fecha_proximo_pago' => ['required', 'date', 'after_or_equal:fecha_transaccion'],
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
            'metodo_pago.required' => 'El método de pago es requerido.',
            'metodo_pago.max' => 'El método de pago no puede exceder 255 caracteres.',
            'monto.required' => 'El monto es requerido.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor o igual a 0.',
            'estado.required' => 'El estado es requerido.',
            'estado.in' => 'El estado debe ser: Completado, Rechazado o Pendiente.',
            'fecha_transaccion.required' => 'La fecha de transacción es requerida.',
            'fecha_transaccion.date' => 'La fecha de transacción debe ser una fecha válida.',
            'fecha_proximo_pago.required' => 'La fecha del próximo pago es requerida.',
            'fecha_proximo_pago.date' => 'La fecha del próximo pago debe ser una fecha válida.',
            'fecha_proximo_pago.after_or_equal' => 'La fecha del próximo pago debe ser igual o posterior a la fecha de transacción.',
        ];
    }
}

