<?php

namespace App\Http\Requests\Compras;

use Illuminate\Foundation\Http\FormRequest;

class GenerarCompraDesdeOrdenCompraRequest extends FormRequest
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
            'id' => 'required|integer|exists:ventas,id',
            'num_orden' => 'required|integer|exists:compras,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la venta es obligatorio.',
            'id.exists' => 'La venta no existe.',
            'num_orden.required' => 'El número de orden es obligatorio.',
            'num_orden.exists' => 'La orden de compra no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'venta',
            'num_orden' => 'orden de compra',
        ];
    }
}

