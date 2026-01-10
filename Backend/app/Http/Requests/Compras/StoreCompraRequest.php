<?php

namespace App\Http\Requests\Compras;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
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
            'id' => 'required|integer|exists:compras,id',
            'fecha' => 'required|date',
            'estado' => 'required|string|max:255',
            'forma_pago' => 'required|string|max:255',
            'id_proveedor' => 'required|integer|exists:proveedores,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'id_bodega' => 'required|integer|exists:sucursal_bodegas,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
            'id_usuario' => 'required|integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la compra es obligatorio.',
            'id.exists' => 'La compra no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'id_proveedor.required' => 'El proveedor es obligatorio.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'estado' => 'estado',
            'forma_pago' => 'forma de pago',
            'id_proveedor' => 'proveedor',
            'id_empresa' => 'empresa',
            'id_bodega' => 'bodega',
            'id_sucursal' => 'sucursal',
            'id_usuario' => 'usuario',
        ];
    }
}

