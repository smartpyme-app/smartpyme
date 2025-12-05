<?php

namespace App\Http\Requests\Compras\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleDevolucionCompraRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:detalles_devolucion_compra,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'no_sujeta' => ['nullable', 'numeric', 'min:0'],
            'exenta' => ['nullable', 'numeric', 'min:0'],
            'gravada' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'iva' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'id_devolucion_compra' => ['nullable', 'integer', 'exists:devoluciones_compra,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El detalle seleccionado no existe.',
            'producto_id.required' => 'El producto es requerido.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo no puede ser negativo.',
            'descuento.numeric' => 'El descuento debe ser un número.',
            'descuento.min' => 'El descuento no puede ser negativo.',
            'no_sujeta.numeric' => 'El campo no sujeta debe ser un número.',
            'no_sujeta.min' => 'El campo no sujeta no puede ser negativo.',
            'exenta.numeric' => 'El campo exenta debe ser un número.',
            'exenta.min' => 'El campo exenta no puede ser negativo.',
            'gravada.numeric' => 'El campo gravada debe ser un número.',
            'gravada.min' => 'El campo gravada no puede ser negativo.',
            'subtotal.numeric' => 'El subtotal debe ser un número.',
            'subtotal.min' => 'El subtotal no puede ser negativo.',
            'iva.numeric' => 'El IVA debe ser un número.',
            'iva.min' => 'El IVA no puede ser negativo.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_devolucion_compra.exists' => 'La devolución de compra seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        $numericFields = ['cantidad', 'costo', 'descuento', 'no_sujeta', 'exenta', 'gravada', 'subtotal', 'iva', 'total'];
        
        foreach ($numericFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => (float) $this->$field]);
            }
        }
    }
}

