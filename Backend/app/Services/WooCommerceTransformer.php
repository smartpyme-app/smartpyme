<?php

namespace App\Services;

class WooCommerceTransformer
{
    /**
     * Transforma datos de cliente de WooCommerce al formato de tu sistema
     */
    public function transformarCliente($wooData)
    {
        return [
            'nombre' => $wooData['billing']['first_name'],
            'apellido' => $wooData['billing']['last_name'],
            'nombre_empresa' => $wooData['billing']['company'],
            'telefono' => $wooData['billing']['phone'],
            'correo' => $wooData['billing']['email'],
            'direccion' => $wooData['billing']['address_1'],
            'pais' => $wooData['billing']['country'],
            'cod_pais' => $wooData['billing']['country'],
            'tipo' => 'Persona',
            'empresa_telefono' => $wooData['billing']['phone'],
            'empresa_direccion' => $wooData['billing']['address_1'],
            'enable' => 1,
            'id_empresa' => $wooData['id_empresa'],
            'id_usuario' => $wooData['id_usuario'],
        ];
    }

    /**
     * Transforma datos de venta de WooCommerce
     */
    public function transformarVenta($wooData, $clienteId, $documentoId)
    {
        return [
            'codigo_generacion' => null, // para DTE si es necesario
            'estado' => 'Pagada',
            'forma_pago' => 'Tarjeta de crédito/débito',
            'observaciones' => $wooData['customer_note'],
            'fecha' => $wooData['date_created'],
            'fecha_pago' => $wooData['date_created'],
            'total_costo' => 0, // calcular basado en detalles
            'total' => $wooData['total'],
            'sub_total' => $wooData['total'],
            'gravada' => $wooData['total'] - $wooData['total_tax'],
            'cuenta_a_terceros' => 0,
            'iva' => $wooData['total_tax'],
            'iva_retenido' => 0,
            'iva_percibido' => 0,
            'descuento' => $wooData['discount_total'],
            'id_cliente' => $clienteId,
            'correlativo' => $wooData['number'],
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
        return [
            'cantidad' => $lineItem['quantity'],
            'costo' => 0, // obtener del producto en tu sistema
            'precio' => $lineItem['price'],
            'total' => $lineItem['total'],
            'total_costo' => 0, // calcular
            'descuento' => $lineItem['subtotal'] - $lineItem['total'],
            'no_sujeta' => 0,
            'exenta' => 0,
            'cuenta_a_terceros' => 0,
            'subtotal' => $lineItem['subtotal'],
            'gravada' => $lineItem['total'],
            'iva' => $lineItem['total_tax'],
            'descripcion' => $lineItem['name'],
            'id_producto' => null, // buscar por SKU en tu sistema
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
