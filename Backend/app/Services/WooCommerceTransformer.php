<?php

namespace App\Services;

class WooCommerceTransformer
{
    /**
     * Transforma datos de cliente de WooCommerce al formato de tu sistema.
     * Soporta 'billing' (REST API) y 'billing_address' (algunos webhooks).
     */
    public function transformarCliente($wooData)
    {
        $billing = $wooData['billing'] ?? $wooData['billing_address'] ?? [];

        $correo = $billing['email'] ?? $wooData['email'] ?? 'woocommerce-' . ($wooData['id'] ?? uniqid()) . '@cliente.temp';

        return [
            'nombre' => $billing['first_name'] ?? '',
            'apellido' => $billing['last_name'] ?? '',
            'nombre_empresa' => $billing['company'] ?? '',
            'telefono' => $billing['phone'] ?? '',
            'correo' => $correo,
            'direccion' => $billing['address_1'] ?? '',
            'pais' => $billing['country'] ?? '',
            'cod_pais' => $billing['country'] ?? '',
            'tipo' => 'Persona',
            'empresa_telefono' => $billing['phone'] ?? '',
            'empresa_direccion' => $billing['address_1'] ?? '',
            'enable' => 1,
            'id_empresa' => $wooData['id_empresa'],
            'id_usuario' => $wooData['id_usuario'],
        ];
    }

    /**
     * Transforma datos de venta de WooCommerce
     */
    public function transformarVenta($wooData, $clienteId, $documentoId, $correlativo)
    {
        $total = (float) ($wooData['total'] ?? 0);
        $totalTax = (float) ($wooData['total_tax'] ?? 0);
        $discountTotal = (float) ($wooData['discount_total'] ?? 0);

        return [
            'codigo_generacion' => null,
            'estado' => 'Pagada',
            'forma_pago' => 'Tarjeta de crédito/débito',
            'observaciones' => $wooData['customer_note'] ?? '',
            'fecha' => $wooData['date_created'] ?? now()->toISOString(),
            'fecha_pago' => $wooData['date_paid'] ?? $wooData['date_created'] ?? now()->toISOString(),
            'total_costo' => 0,
            'total' => $total,
            'sub_total' => $total,
            'gravada' => $total - $totalTax,
            'cuenta_a_terceros' => 0,
            'iva' => $totalTax,
            'iva_retenido' => 0,
            'iva_percibido' => 0,
            'descuento' => $discountTotal,
            'id_cliente' => $clienteId,
            'correlativo' => $correlativo,
            'id_documento' => $documentoId,
            'id_bodega' => $wooData['id_bodega'],
            'id_empresa' => $wooData['id_empresa'],
            'id_usuario' => $wooData['id_usuario'],
            'id_sucursal' => $wooData['id_sucursal'],
            'id_canal' => $wooData['id_canal'],
        ];
    }

    /**
     * Transforma líneas de items a detalles de venta
     */
    public function transformarDetallesVenta($lineItem, $ventaId)
    {
        $subtotal = (float) ($lineItem['subtotal'] ?? 0);
        $total = (float) ($lineItem['total'] ?? 0);
        $totalTax = (float) ($lineItem['total_tax'] ?? 0);

        return [
            'cantidad' => (float) ($lineItem['quantity'] ?? 0),
            'costo' => 0,
            'precio' => (float) ($lineItem['price'] ?? 0),
            'total' => $total,
            'total_costo' => 0,
            'descuento' => max(0, $subtotal - $total),
            'no_sujeta' => 0,
            'exenta' => 0,
            'cuenta_a_terceros' => 0,
            'subtotal' => $subtotal,
            'gravada' => $total,
            'iva' => $totalTax,
            'descripcion' => $lineItem['name'] ?? '',
            'id_producto' => null,
            'id_venta' => $ventaId
        ];
    }

    /**
     * Actualiza el inventario
     */
    public function actualizarInventario($productoId, $cantidad, $bodegaId)
    {
        return [
            'id_producto' => $productoId,
            'id_bodega' => $bodegaId,
            'stock' => ['decrement' => $cantidad],
            'updated_at' => now()
        ];
    }

    private function mapearEstado($wooStatus)
    {
        $mapeo = [
            'pending' => 'Pendiente',
            'processing' => 'En Proceso',
            'completed' => 'Completada',
            'cancelled' => 'Anulada',
            'refunded' => 'Reembolsada',
            'failed' => 'Fallida'
        ];

        return $mapeo[$wooStatus] ?? 'Pendiente';
    }
    //transformarProducto
    public function transformarProducto($wooData, $id_empresa, $id_usuario, $id_sucursal)
    {
        return [
            'barcode' => $wooData['sku'],
            'nombre' => $wooData['name'],
            'descripcion' => isset($wooData['description']) ? $wooData['description'] : '',
            'id_empresa' => $id_empresa,
            'id_usuario' => $id_usuario,
            'id_sucursal' => $id_sucursal,
            'costo' => $wooData['price'],
            'precio' => $wooData['price'],
        ];
    }
}