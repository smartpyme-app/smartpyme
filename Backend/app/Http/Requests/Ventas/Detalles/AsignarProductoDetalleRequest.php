<?php

namespace App\Http\Requests\Ventas\Detalles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarProductoDetalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = auth()->user()?->id_empresa;

        return [
            'id_producto' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('tipo', 'Servicio');
                    if ($empresaId !== null) {
                        $query->where('id_empresa', $empresaId);
                    }
                }),
            ],
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
