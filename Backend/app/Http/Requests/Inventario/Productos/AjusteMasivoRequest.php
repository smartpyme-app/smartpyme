<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class AjusteMasivoRequest extends FormRequest
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
            'detalle' => 'required|string|max:255',
            'productos' => 'required|array|min:1',
            'productos.*.id_producto' => 'required|integer|exists:productos,id',
            'productos.*.id_bodega' => 'required|integer|exists:sucursal_bodegas,id',
            'productos.*.stock_actual' => 'required|numeric|min:0',
            'productos.*.stock_nuevo' => 'required|numeric|min:0',
            'productos.*.diferencia' => 'required|numeric',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'detalle.required' => 'El detalle es obligatorio.',
            'detalle.max' => 'El detalle no puede exceder 255 caracteres.',
            'productos.required' => 'Los productos son obligatorios.',
            'productos.array' => 'Los productos deben ser un array.',
            'productos.min' => 'Debe haber al menos un producto.',
            'productos.*.id_producto.required' => 'El producto es obligatorio en cada elemento.',
            'productos.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'productos.*.id_bodega.required' => 'La bodega es obligatoria en cada elemento.',
            'productos.*.id_bodega.exists' => 'Una de las bodegas seleccionadas no existe.',
            'productos.*.stock_actual.required' => 'El stock actual es obligatorio en cada elemento.',
            'productos.*.stock_actual.numeric' => 'El stock actual debe ser un número.',
            'productos.*.stock_actual.min' => 'El stock actual no puede ser negativo.',
            'productos.*.stock_nuevo.required' => 'El stock nuevo es obligatorio en cada elemento.',
            'productos.*.stock_nuevo.numeric' => 'El stock nuevo debe ser un número.',
            'productos.*.stock_nuevo.min' => 'El stock nuevo no puede ser negativo.',
            'productos.*.diferencia.required' => 'La diferencia es obligatoria en cada elemento.',
            'productos.*.diferencia.numeric' => 'La diferencia debe ser un número.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'detalle' => 'detalle',
            'productos' => 'productos',
        ];
    }
}

