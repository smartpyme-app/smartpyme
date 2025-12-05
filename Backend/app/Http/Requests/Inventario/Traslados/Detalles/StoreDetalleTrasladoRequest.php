<?php

namespace App\Http\Requests\Inventario\Traslados\Detalles;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleTrasladoRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:traslado_detalles,id'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'traslado_id' => ['required', 'integer', 'exists:traslados,id'],
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
            'traslado_id.required' => 'El traslado es requerido.',
            'traslado_id.exists' => 'El traslado seleccionado no existe.',
        ];
    }
}

