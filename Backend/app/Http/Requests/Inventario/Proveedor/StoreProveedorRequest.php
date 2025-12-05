<?php

namespace App\Http\Requests\Inventario\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

class StoreProveedorRequest extends FormRequest
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
            'id_proveedor' => ['required', 'integer', 'exists:proveedores,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_proveedor.required' => 'El proveedor es requerido.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
        ];
    }
}

