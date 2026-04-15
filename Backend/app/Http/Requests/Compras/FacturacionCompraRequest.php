<?php

namespace App\Http\Requests\Compras;

use Illuminate\Foundation\Http\FormRequest;

class FacturacionCompraRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:compras,id',
            'fecha' => 'required|date',
            'estado' => 'required|string|max:255',
            'tipo_documento' => 'required|string|max:255',
            'forma_pago' => 'required|string|max:255',
            'id_proveedor' => 'required|integer|exists:proveedores,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.id' => 'sometimes|nullable|integer|exists:detalles,id',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.costo' => 'required|numeric|min:0',
            'detalles.*.total' => 'required|numeric|min:0',
            'referencia' => 'required_if:estado,"Pre-compra"|nullable|max:255',
            'id_usuario' => 'required|integer|exists:users,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La compra no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento.max' => 'El tipo de documento no puede exceder 255 caracteres.',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'detalles.required' => 'Los detalles son obligatorios.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.costo.required' => 'El costo es obligatorio en cada detalle.',
            'detalles.*.costo.min' => 'El costo no puede ser negativo.',
            'detalles.*.total.required' => 'El total es obligatorio en cada detalle.',
            'detalles.*.total.min' => 'El total no puede ser negativo.',
            'referencia.required_if' => 'La referencia es obligatoria cuando el estado es Pre-compra.',
            'referencia.max' => 'La referencia no puede exceder 255 caracteres.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
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
            'tipo_documento' => 'tipo de documento',
            'forma_pago' => 'forma de pago',
            'id_proveedor' => 'proveedor',
            'detalles' => 'detalles',
            'referencia' => 'referencia',
            'id_usuario' => 'usuario',
            'id_empresa' => 'empresa',
        ];
    }
}

