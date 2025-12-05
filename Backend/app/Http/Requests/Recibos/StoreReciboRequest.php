<?php

namespace App\Http\Requests\Recibos;

use Illuminate\Foundation\Http\FormRequest;

class StoreReciboRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0'],
            'forma_pago' => ['required', 'string', 'max:255'],
            'concepto' => ['required', 'string', 'max:255'],
            'id_venta' => ['required', 'integer', 'exists:ventas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'monto.required' => 'El monto es requerido.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto no puede ser negativo.',
            'forma_pago.required' => 'La forma de pago es requerida.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'id_venta.required' => 'La venta es requerida.',
            'id_venta.exists' => 'La venta seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('concepto')) {
            $this->merge(['concepto' => trim($this->concepto)]);
        }

        if ($this->has('forma_pago')) {
            $this->merge(['forma_pago' => trim($this->forma_pago)]);
        }

        // Convertir valores numéricos
        if ($this->has('monto')) {
            $this->merge(['monto' => (float) $this->monto]);
        }
    }
}

