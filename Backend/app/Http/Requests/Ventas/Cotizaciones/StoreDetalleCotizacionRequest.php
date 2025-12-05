<?php

namespace App\Http\Requests\Ventas\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleCotizacionRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:venta_detalles,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'precio' => ['required', 'numeric', 'min:0'],
            'costo' => ['required', 'numeric', 'min:0'],
            'descuento' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'nota' => ['nullable', 'string', 'max:255'],
            'venta_id' => ['required', 'integer', 'exists:ventas,id'],
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
            'precio.required' => 'El precio es requerido.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio debe ser mayor o igual a 0.',
            'costo.required' => 'El costo es requerido.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo debe ser mayor o igual a 0.',
            'descuento.required' => 'El descuento es requerido.',
            'descuento.numeric' => 'El descuento debe ser un número.',
            'descuento.min' => 'El descuento debe ser mayor o igual a 0.',
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total debe ser mayor o igual a 0.',
            'nota.max' => 'La nota no puede exceder 255 caracteres.',
            'venta_id.required' => 'La venta es requerida.',
            'venta_id.exists' => 'La venta seleccionada no existe.',
        ];
    }
}

