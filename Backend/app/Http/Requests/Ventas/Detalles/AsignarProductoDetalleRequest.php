<?php

namespace App\Http\Requests\Ventas\Detalles;

use Illuminate\Foundation\Http\FormRequest;

class AsignarProductoDetalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_producto.required' => 'Debe seleccionar un producto o servicio.',
            'id_producto.exists' => 'El producto o servicio seleccionado no existe.',
        ];
    }
}
