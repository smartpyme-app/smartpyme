<?php

namespace App\Http\Requests\Compras\Detalles;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleCompraRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:detalles_compra,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'costo' => ['required', 'numeric', 'min:0'],
            'id_compra' => ['required', 'integer', 'exists:compras,id'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'tanque_id' => ['nullable', 'integer', 'exists:tanques,id'],
            'bodega_id' => ['nullable', 'integer', 'exists:sucursal_bodegas,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'no_sujeta' => ['nullable', 'numeric', 'min:0'],
            'exenta' => ['nullable', 'numeric', 'min:0'],
            'iva' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El detalle seleccionado no existe.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'costo.required' => 'El costo es requerido.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo no puede ser negativo.',
            'id_compra.required' => 'La compra es requerida.',
            'id_compra.exists' => 'La compra seleccionada no existe.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
            'tanque_id.exists' => 'El tanque seleccionado no existe.',
            'bodega_id.exists' => 'La bodega seleccionada no existe.',
            'descuento.numeric' => 'El descuento debe ser un número.',
            'descuento.min' => 'El descuento no puede ser negativo.',
            'no_sujeta.numeric' => 'El campo no sujeta debe ser un número.',
            'no_sujeta.min' => 'El campo no sujeta no puede ser negativo.',
            'exenta.numeric' => 'El campo exenta debe ser un número.',
            'exenta.min' => 'El campo exenta no puede ser negativo.',
            'iva.numeric' => 'El IVA debe ser un número.',
            'iva.min' => 'El IVA no puede ser negativo.',
            'subtotal.numeric' => 'El subtotal debe ser un número.',
            'subtotal.min' => 'El subtotal no puede ser negativo.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('cantidad')) {
            $this->merge(['cantidad' => (float) $this->cantidad]);
        }

        if ($this->has('costo')) {
            $this->merge(['costo' => (float) $this->costo]);
        }

        if ($this->has('precio')) {
            $this->merge(['precio' => (float) $this->precio]);
        }

        if ($this->has('descuento')) {
            $this->merge(['descuento' => (float) $this->descuento]);
        }

        if ($this->has('no_sujeta')) {
            $this->merge(['no_sujeta' => (float) $this->no_sujeta]);
        }

        if ($this->has('exenta')) {
            $this->merge(['exenta' => (float) $this->exenta]);
        }

        if ($this->has('iva')) {
            $this->merge(['iva' => (float) $this->iva]);
        }

        if ($this->has('subtotal')) {
            $this->merge(['subtotal' => (float) $this->subtotal]);
        }

        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }
    }
}

