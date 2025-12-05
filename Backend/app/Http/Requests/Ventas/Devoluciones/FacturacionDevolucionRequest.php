<?php

namespace App\Http\Requests\Ventas\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class FacturacionDevolucionRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:devoluciones_venta,id',
            'fecha' => 'required|date',
            'tipo' => 'required|string|in:devolucion,descuento_ajuste,anulacion_factura',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'total_costo' => 'required|numeric|min:0',
            'sub_total' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'observaciones' => 'required|string|max:500',
            'id_venta' => 'required|integer|exists:ventas,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'id_bodega' => 'required|integer|exists:bodegas,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
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
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser: devolucion, descuento_ajuste o anulacion_factura.',
            'detalles.required' => 'Tienes que ingresar los detalles a devolver.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.precio.required' => 'El precio es obligatorio en cada detalle.',
            'detalles.*.precio.min' => 'El precio no puede ser negativo.',
            'iva.required' => 'El IVA es obligatorio.',
            'iva.numeric' => 'El IVA debe ser un número.',
            'iva.min' => 'El IVA no puede ser negativo.',
            'total_costo.required' => 'El total de costo es obligatorio.',
            'total_costo.numeric' => 'El total de costo debe ser un número.',
            'total_costo.min' => 'El total de costo no puede ser negativo.',
            'sub_total.required' => 'El subtotal es obligatorio.',
            'sub_total.numeric' => 'El subtotal debe ser un número.',
            'sub_total.min' => 'El subtotal no puede ser negativo.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'observaciones.required' => 'Las observaciones son obligatorias.',
            'observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres.',
            'id_venta.required' => 'La venta es obligatoria.',
            'id_venta.exists' => 'La venta seleccionada no existe.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
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
            'tipo' => 'tipo',
            'detalles' => 'detalles',
            'iva' => 'IVA',
            'total_costo' => 'total de costo',
            'sub_total' => 'subtotal',
            'total' => 'total',
            'observaciones' => 'observaciones',
            'id_venta' => 'venta',
            'id_usuario' => 'usuario',
            'id_bodega' => 'bodega',
            'id_sucursal' => 'sucursal',
            'id_empresa' => 'empresa',
        ];
    }
}

