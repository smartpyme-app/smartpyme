<?php

namespace App\Http\Requests\Compras\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevolucionCompraRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:devoluciones_compras,id',
            'fecha' => 'required|date',
            'enable' => 'required|boolean',
            'id_proveedor' => 'required|integer|exists:proveedores,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'tipo' => 'required|string|in:devolucion,descuento_ajuste,anulacion_factura',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La devolución no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'enable.required' => 'El estado enable es obligatorio.',
            'enable.boolean' => 'El estado enable debe ser verdadero o falso.',
            'id_proveedor.required' => 'El proveedor es obligatorio.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser: devolucion, descuento_ajuste o anulacion_factura.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'enable' => 'estado',
            'id_proveedor' => 'proveedor',
            'id_usuario' => 'usuario',
            'tipo' => 'tipo',
        ];
    }
}

