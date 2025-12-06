<?php

namespace App\Http\Requests\Compras;

use Illuminate\Foundation\Http\FormRequest;

class FacturacionConsignaRequest extends FormRequest
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
            'tipo_documento' => 'required|string|max:255',
            'id_proveedor' => 'required|integer|exists:proveedores,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.id' => 'sometimes|nullable|integer|exists:detalles,id',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.costo' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'forma_pago' => 'required_if:metodo_pago,"Crédito"|nullable|string|max:255',
            'sub_total' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'nota' => 'sometimes|nullable|string|max:255',
            'id_usuario' => 'required|integer|exists:users,id',
            'id_bodega' => 'required|integer|exists:bodegas,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
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
            'tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento.max' => 'El tipo de documento no puede exceder 255 caracteres.',
            'id_proveedor.required' => 'El proveedor es obligatorio.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'detalles.required' => 'Tiene que agregar productos a la venta',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.costo.required' => 'El costo es obligatorio en cada detalle.',
            'detalles.*.costo.min' => 'El costo no puede ser negativo.',
            'iva.required' => 'El IVA es obligatorio.',
            'iva.numeric' => 'El IVA debe ser un número.',
            'iva.min' => 'El IVA no puede ser negativo.',
            'forma_pago.required_if' => 'La forma de pago es obligatoria cuando el método de pago es Crédito.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'sub_total.required' => 'El subtotal es obligatorio.',
            'sub_total.numeric' => 'El subtotal debe ser un número.',
            'sub_total.min' => 'El subtotal no puede ser negativo.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'nota.max' => 'La nota no puede exceder 255 caracteres.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
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
            'id_proveedor' => 'proveedor',
            'detalles' => 'detalles',
            'iva' => 'IVA',
            'forma_pago' => 'forma de pago',
            'sub_total' => 'subtotal',
            'total' => 'total',
            'nota' => 'nota',
            'id_usuario' => 'usuario',
            'id_bodega' => 'bodega',
            'id_sucursal' => 'sucursal',
        ];
    }
}

