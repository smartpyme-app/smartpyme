<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class TrasladoMasivoRequest extends FormRequest
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
            'concepto' => 'required|string|max:500',
            'id_bodega_origen' => 'required|integer|exists:bodegas,id',
            'id_bodega_destino' => 'required|integer|exists:bodegas,id|different:id_bodega_origen',
            'id_usuario' => 'required|integer|exists:users,id',
            'productos' => 'required|array|min:1',
            'productos.*.id_producto' => 'required|integer|exists:productos,id',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'concepto.required' => 'El concepto es obligatorio.',
            'concepto.max' => 'El concepto no puede exceder 500 caracteres.',
            'id_bodega_origen.required' => 'La bodega de origen es obligatoria.',
            'id_bodega_origen.exists' => 'La bodega de origen seleccionada no existe.',
            'id_bodega_destino.required' => 'La bodega de destino es obligatoria.',
            'id_bodega_destino.exists' => 'La bodega de destino seleccionada no existe.',
            'id_bodega_destino.different' => 'La bodega de destino debe ser diferente a la bodega de origen.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'productos.required' => 'Los productos son obligatorios.',
            'productos.array' => 'Los productos deben ser un array.',
            'productos.min' => 'Debe haber al menos un producto.',
            'productos.*.id_producto.required' => 'El producto es obligatorio en cada elemento.',
            'productos.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'productos.*.cantidad.required' => 'La cantidad es obligatoria en cada elemento.',
            'productos.*.cantidad.numeric' => 'La cantidad debe ser un número.',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'concepto' => 'concepto',
            'id_bodega_origen' => 'bodega de origen',
            'id_bodega_destino' => 'bodega de destino',
            'id_usuario' => 'usuario',
            'productos' => 'productos',
        ];
    }
}

