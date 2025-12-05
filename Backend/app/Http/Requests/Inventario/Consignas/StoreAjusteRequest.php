<?php

namespace App\Http\Requests\Inventario\Consignas;

use Illuminate\Foundation\Http\FormRequest;

class StoreAjusteRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:ajustes,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'stock_actual' => ['required', 'numeric'],
            'stock_real' => ['required', 'numeric'],
            'ajuste' => ['required', 'numeric'],
            'concepto' => ['required', 'string', 'max:255'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El ajuste seleccionado no existe.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'stock_actual.required' => 'El stock actual es requerido.',
            'stock_actual.numeric' => 'El stock actual debe ser un número.',
            'stock_real.required' => 'El stock real es requerido.',
            'stock_real.numeric' => 'El stock real debe ser un número.',
            'ajuste.required' => 'El ajuste es requerido.',
            'ajuste.numeric' => 'El ajuste debe ser un número.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}

