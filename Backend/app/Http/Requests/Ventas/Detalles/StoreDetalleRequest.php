<?php

namespace App\Http\Requests\Ventas\Detalles;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:detalles,id',
            'producto_id' => 'required|integer|exists:productos,id',
            'cantidad' => 'required|numeric|min:0.01',
            'precio' => 'required|numeric|min:0',
            'costo' => 'required|numeric|min:0',
            'venta_id' => 'required|integer|exists:ventas,id',
            'bomba_id' => 'sometimes|nullable|integer|exists:bombas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El detalle no existe.',
            'producto_id.required' => 'El producto es obligatorio.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
            'costo.required' => 'El costo es obligatorio.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo no puede ser negativo.',
            'venta_id.required' => 'La venta es obligatoria.',
            'venta_id.exists' => 'La venta seleccionada no existe.',
            'bomba_id.exists' => 'La bomba seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'producto_id' => 'producto',
            'cantidad' => 'cantidad',
            'precio' => 'precio',
            'costo' => 'costo',
            'venta_id' => 'venta',
            'bomba_id' => 'bomba',
        ];
    }
}

