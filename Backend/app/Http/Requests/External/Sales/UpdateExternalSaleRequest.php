<?php

namespace App\Http\Requests\External\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'estado' => ['sometimes', 'string', 'in:Pagada,Completada,Pendiente,Cotizacion,Pre-venta'],
            'id_sucursal' => ['sometimes', 'integer', 'min:1'],
            'sucursal' => ['sometimes', 'string', 'max:255'],
            'id_bodega' => ['sometimes', 'integer', 'min:1'],
            'id_documento' => ['sometimes', 'integer', 'min:1'],
            'id_canal' => ['sometimes', 'integer', 'min:1'],
            'id_cliente' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'cotizacion' => ['sometimes', 'boolean'],
            'fecha_expiracion' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'forma_pago' => ['sometimes', 'nullable', 'string', 'max:100'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'monto_pago' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cambio' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'detalles' => ['sometimes', 'array', 'min:1', 'max:100'],
            'detalles.*.id' => ['sometimes', 'integer', 'min:1'],
            'detalles.*.id_producto' => ['nullable', 'integer', 'min:1', 'required_without:detalles.*.codigo_producto'],
            'detalles.*.codigo_producto' => ['nullable', 'string', 'max:100', 'required_without:detalles.*.id_producto'],
            'detalles.*.cantidad' => ['required_with:detalles', 'numeric', 'min:0.0001'],
            'detalles.*.precio' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.id_presentacion' => ['nullable', 'integer', 'min:1'],
            'detalles.*.porcentaje_impuesto' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
