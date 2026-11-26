<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaProgramadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'id_cliente' => 'required|integer|exists:clientes,id',
            'id_documento' => 'required|integer|exists:documentos,id',
            'frecuencia' => 'required|in:mensual,anual',
            'dia_ejecucion' => 'required|integer|min:1|max:31',
            'hora_ejecucion' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio' => 'required|numeric|min:0',
            'detalles.*.total' => 'required|numeric|min:0',
            'detalles.*.id_presentacion' => 'nullable|integer|exists:producto_presentaciones,id',
            'detalles.*.descripcion' => 'nullable|string',
            'detalles.*.precio_sin_iva' => 'nullable|numeric|min:0',
            'detalles.*.precio_con_iva' => 'nullable|numeric|min:0',
            'detalles.*.costo' => 'nullable|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.sub_total' => 'nullable|numeric|min:0',
            'detalles.*.tipo_gravado' => 'nullable|string|max:20',
            'detalles.*.no_sujeta' => 'nullable|numeric|min:0',
            'detalles.*.exenta' => 'nullable|numeric|min:0',
            'detalles.*.gravada' => 'nullable|numeric|min:0',
            'detalles.*.cuenta_a_terceros' => 'nullable|numeric|min:0',
            'detalles.*.total_costo' => 'nullable|numeric|min:0',
            'detalles.*.id_vendedor' => 'nullable|integer|exists:users,id',
            'detalles.*.iva' => 'nullable|numeric|min:0',
            'detalles.*.porcentaje_impuesto' => 'nullable|numeric|min:0',
            'detalles.*.fe_cr_exoneracion' => 'nullable|array',
            'id_sucursal' => 'nullable|integer|exists:sucursales,id',
            'id_bodega' => 'nullable|integer|exists:sucursal_bodegas,id',
            'id_vendedor' => 'nullable|integer|exists:users,id',
            'forma_pago' => 'nullable|string|max:255',
            'condicion' => 'nullable|string|max:255',
            'sub_total' => 'nullable|numeric|min:0',
            'descuento' => 'nullable|numeric|min:0',
            'iva' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'gravada' => 'nullable|numeric|min:0',
            'exenta' => 'nullable|numeric|min:0',
            'no_sujeta' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'activo' => 'sometimes|boolean',
            'emitir_automatico' => 'sometimes|boolean',
            'enviar_email' => 'sometimes|boolean',
            'max_ejecuciones' => 'nullable|integer|min:1',
        ];
    }
}