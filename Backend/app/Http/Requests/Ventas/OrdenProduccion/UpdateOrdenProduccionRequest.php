<?php

namespace App\Http\Requests\Ventas\OrdenProduccion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrdenProduccionRequest extends FormRequest
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
            'fecha_entrega' => 'sometimes|nullable|date',
            'observaciones' => 'sometimes|nullable|string|max:1000',
            'detalles' => 'sometimes|nullable|array',
            'detalles.*.id_producto' => 'required_with:detalles|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0.01',
            'detalles.*.precio' => 'required_with:detalles|numeric|min:0',
            'detalles.*.descripcion' => 'sometimes|nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_entrega.date' => 'La fecha de entrega debe tener un formato válido.',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.*.id_producto.required_with' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required_with' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.precio.required_with' => 'El precio es obligatorio en cada detalle.',
            'detalles.*.precio.min' => 'El precio no puede ser negativo.',
            'detalles.*.descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'fecha_entrega' => 'fecha de entrega',
            'observaciones' => 'observaciones',
            'detalles' => 'detalles',
        ];
    }
}

