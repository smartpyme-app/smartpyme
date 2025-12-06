<?php

namespace App\Http\Requests\Compras\Abonos;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbonoCompraRequest extends FormRequest
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
            'total' => ['required', 'numeric', 'min:0'],
            'id_compra' => ['required', 'integer', 'exists:compras,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id' => ['nullable', 'integer', 'exists:abonos_compras,id'],
            'detalle_banco' => ['nullable', 'string'],
            'referencia' => ['nullable', 'string'],
            'mora' => ['nullable', 'numeric', 'min:0'],
            'comision' => ['nullable', 'numeric', 'min:0'],
            'nota' => ['nullable', 'string'],
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
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_compra.required' => 'La compra es requerida.',
            'id_compra.exists' => 'La compra seleccionada no existe.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id.exists' => 'El abono seleccionado no existe.',
            'mora.numeric' => 'La mora debe ser un número.',
            'mora.min' => 'La mora no puede ser negativa.',
            'comision.numeric' => 'La comisión debe ser un número.',
            'comision.min' => 'La comisión no puede ser negativa.',
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

        if ($this->has('nombre_de')) {
            $this->merge(['nombre_de' => trim($this->nombre_de)]);
        }

        if ($this->has('forma_pago')) {
            $this->merge(['forma_pago' => trim($this->forma_pago)]);
        }

        // Convertir valores numéricos
        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }

        if ($this->has('mora')) {
            $this->merge(['mora' => (float) $this->mora]);
        }

        if ($this->has('comision')) {
            $this->merge(['comision' => (float) $this->comision]);
        }
    }
}

