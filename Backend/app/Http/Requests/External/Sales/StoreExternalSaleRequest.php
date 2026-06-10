<?php

namespace App\Http\Requests\External\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date', 'date_format:Y-m-d'],
            'estado' => ['required', 'string', 'in:Pagada,Completada,Pendiente,Cotizacion'],
            'id_sucursal' => ['nullable', 'integer', 'min:1', 'required_without:sucursal'],
            'sucursal' => ['nullable', 'string', 'max:255', 'required_without:id_sucursal'],
            'id_bodega' => ['required', 'integer', 'min:1'],
            'id_documento' => ['required', 'integer', 'min:1'],
            'id_canal' => ['nullable', 'integer', 'min:1'],
            'id_cliente' => ['nullable', 'integer', 'min:1'],
            'cotizacion' => ['nullable', 'boolean'],
            'fecha_expiracion' => ['nullable', 'date', 'date_format:Y-m-d'],
            'forma_pago' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'monto_pago' => ['nullable', 'numeric', 'min:0'],
            'cambio' => ['nullable', 'numeric', 'min:0'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.id_producto' => ['nullable', 'integer', 'min:1', 'required_without:detalles.*.codigo_producto'],
            'detalles.*.codigo_producto' => ['nullable', 'string', 'max:100', 'required_without:detalles.*.id_producto'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'detalles.*.precio' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.id_presentacion' => ['nullable', 'integer', 'min:1'],
            'detalles.*.porcentaje_impuesto' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'detalles.required' => 'Debe incluir al menos un producto en detalles.',
            'detalles.max' => 'No se permiten más de 100 líneas por venta.',
            'id_sucursal.required_without' => 'Debe enviar id_sucursal o sucursal (nombre).',
            'sucursal.required_without' => 'Debe enviar id_sucursal o sucursal (nombre).',
        ];
    }
}
