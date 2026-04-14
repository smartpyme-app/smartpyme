<?php

namespace App\Http\Requests\Compras\Gastos;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbonoGastoRequest extends FormRequest
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
            'concepto' => ['required', 'string', 'max:255'],
            'nombre_de' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'forma_pago' => ['required', 'string', 'max:255'],
            'detalle_banco' => ['required_unless:forma_pago,Efectivo', 'string'],
            'total' => ['required', 'numeric', 'min:0'],
            'id_gasto' => ['required', 'integer', 'exists:egresos,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id' => ['nullable', 'integer', 'exists:abonos_gastos,id'],
            'id_empresa' => ['nullable', 'integer', 'exists:empresas,id'],
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
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'nombre_de.required' => 'El nombre es requerido.',
            'nombre_de.max' => 'El nombre no puede exceder 255 caracteres.',
            'estado.required' => 'El estado es requerido.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'forma_pago.required' => 'La forma de pago es requerida.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'detalle_banco.required_unless' => 'El detalle del banco es requerido cuando la forma de pago no es Efectivo.',
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_gasto.required' => 'El gasto es requerido.',
            'id_gasto.exists' => 'El gasto seleccionado no existe.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id.exists' => 'El abono seleccionado no existe.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }

        // Limpiar strings
        if ($this->has('concepto')) {
            $this->merge(['concepto' => trim($this->concepto)]);
        }

        if ($this->has('nombre_de')) {
            $this->merge(['nombre_de' => trim($this->nombre_de)]);
        }

        if ($this->has('detalle_banco')) {
            $this->merge(['detalle_banco' => trim($this->detalle_banco)]);
        }
    }
}

