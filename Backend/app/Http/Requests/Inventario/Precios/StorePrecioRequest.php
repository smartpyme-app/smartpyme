<?php

namespace App\Http\Requests\Inventario\Precios;

use Illuminate\Foundation\Http\FormRequest;

class StorePrecioRequest extends FormRequest
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
            'precio' => ['required', 'numeric', 'min:0'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'usuarios' => ['required', 'array', 'min:1'],
            'usuarios.*.id' => ['required', 'integer', 'exists:users,id'],
            'usuarios.*.autorizado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'precio.required' => 'El precio es requerido.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio debe ser mayor o igual a 0.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'usuarios.required' => 'Los usuarios son requeridos.',
            'usuarios.array' => 'Los usuarios deben ser un array.',
            'usuarios.min' => 'Debe haber al menos un usuario.',
            'usuarios.*.id.required' => 'El ID del usuario es requerido.',
            'usuarios.*.id.exists' => 'El usuario seleccionado no existe.',
            'usuarios.*.autorizado.boolean' => 'El campo autorizado debe ser verdadero o falso.',
        ];
    }
}

