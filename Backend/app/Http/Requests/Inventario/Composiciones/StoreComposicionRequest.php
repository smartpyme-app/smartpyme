<?php

namespace App\Http\Requests\Inventario\Composiciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreComposicionRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:composiciones,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_compuesto' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La composición seleccionada no existe.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_compuesto.required' => 'El producto compuesto es requerido.',
            'id_compuesto.exists' => 'El producto compuesto seleccionado no existe.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }
}

