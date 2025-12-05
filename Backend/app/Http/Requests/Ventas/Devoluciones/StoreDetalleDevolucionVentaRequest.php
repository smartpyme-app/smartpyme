<?php

namespace App\Http\Requests\Ventas\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleDevolucionVentaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:detalles_devolucion_venta,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'no_sujeta' => ['nullable', 'numeric', 'min:0'],
            'exenta' => ['nullable', 'numeric', 'min:0'],
            'cuenta_a_terceros' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'id_devolucion_venta' => ['nullable', 'integer', 'exists:devoluciones_venta,id'],
            'descripcion' => ['nullable', 'string', 'max:500'],
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
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo no puede ser negativo.',
            'descuento.numeric' => 'El descuento debe ser un número.',
            'descuento.min' => 'El descuento no puede ser negativo.',
            'no_sujeta.numeric' => 'El campo no sujeta debe ser un número.',
            'no_sujeta.min' => 'El campo no sujeta no puede ser negativo.',
            'exenta.numeric' => 'El campo exenta debe ser un número.',
            'exenta.min' => 'El campo exenta no puede ser negativo.',
            'cuenta_a_terceros.numeric' => 'El campo cuenta a terceros debe ser un número.',
            'cuenta_a_terceros.min' => 'El campo cuenta a terceros no puede ser negativo.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_devolucion_venta.exists' => 'La devolución de venta seleccionada no existe.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        $numericFields = ['cantidad', 'precio', 'costo', 'descuento', 'no_sujeta', 'exenta', 'cuenta_a_terceros', 'total'];
        
        foreach ($numericFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => (float) $this->$field]);
            }
        }

        // Limpiar strings
        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => trim($this->descripcion)]);
        }
    }
}

