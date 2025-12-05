<?php

namespace App\Http\Requests\Inventario\Traslados;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrasladoRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:traslados,id'],
            'fecha' => ['required', 'date'],
            'estado' => ['required', 'string', 'max:255'],
            'id_bodega_de' => ['required', 'integer', 'exists:bodegas,id'],
            'id_bodega' => ['required', 'integer', 'exists:bodegas,id', 'different:id_bodega_de'],
            'concepto' => ['required', 'string', 'max:255'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El traslado seleccionado no existe.',
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'estado.required' => 'El estado es requerido.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'id_bodega_de.required' => 'La bodega de origen es requerida.',
            'id_bodega_de.exists' => 'La bodega de origen seleccionada no existe.',
            'id_bodega.required' => 'La bodega de destino es requerida.',
            'id_bodega.exists' => 'La bodega de destino seleccionada no existe.',
            'id_bodega.different' => 'La bodega de destino debe ser diferente a la bodega de origen.',
            'concepto.required' => 'El campo nota es obligatorio.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'detalles.required' => 'Los detalles son requeridos.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto en el detalle es requerido.',
            'detalles.*.id_producto.exists' => 'El producto en el detalle no existe.',
            'detalles.*.cantidad.required' => 'La cantidad en el detalle es requerida.',
            'detalles.*.cantidad.numeric' => 'La cantidad en el detalle debe ser un número.',
            'detalles.*.cantidad.min' => 'La cantidad en el detalle debe ser mayor a 0.',
        ];
    }
}

