<?php

namespace App\Http\Requests\Inventario\Kardex;

use Illuminate\Foundation\Http\FormRequest;

class StoreKardexRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:kardexs,id'],
            'fecha' => ['required', 'date'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'detalle' => ['required', 'string'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'entrada_cantidad' => ['required', 'numeric', 'min:0'],
            'entrada_valor' => ['required', 'numeric', 'min:0'],
            'salida_cantidad' => ['required', 'numeric', 'min:0'],
            'salida_valor' => ['required', 'numeric', 'min:0'],
            'total_cantidad' => ['required', 'numeric', 'min:0'],
            'total_valor' => ['required', 'numeric', 'min:0'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El kardex seleccionado no existe.',
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'detalle.required' => 'El detalle es requerido.',
            'referencia.max' => 'La referencia no puede exceder 255 caracteres.',
            'entrada_cantidad.required' => 'La cantidad de entrada es requerida.',
            'entrada_cantidad.numeric' => 'La cantidad de entrada debe ser un número.',
            'entrada_cantidad.min' => 'La cantidad de entrada debe ser mayor o igual a 0.',
            'entrada_valor.required' => 'El valor de entrada es requerido.',
            'entrada_valor.numeric' => 'El valor de entrada debe ser un número.',
            'entrada_valor.min' => 'El valor de entrada debe ser mayor o igual a 0.',
            'salida_cantidad.required' => 'La cantidad de salida es requerida.',
            'salida_cantidad.numeric' => 'La cantidad de salida debe ser un número.',
            'salida_cantidad.min' => 'La cantidad de salida debe ser mayor o igual a 0.',
            'salida_valor.required' => 'El valor de salida es requerido.',
            'salida_valor.numeric' => 'El valor de salida debe ser un número.',
            'salida_valor.min' => 'El valor de salida debe ser mayor o igual a 0.',
            'total_cantidad.required' => 'La cantidad total es requerida.',
            'total_cantidad.numeric' => 'La cantidad total debe ser un número.',
            'total_cantidad.min' => 'La cantidad total debe ser mayor o igual a 0.',
            'total_valor.required' => 'El valor total es requerido.',
            'total_valor.numeric' => 'El valor total debe ser un número.',
            'total_valor.min' => 'El valor total debe ser mayor o igual a 0.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}

