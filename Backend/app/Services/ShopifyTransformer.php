<?php

namespace App\Services;

class ShopifyTransformer
{
    
    public function transformarCliente($shopifyData)
    {
        $customer = $shopifyData['customer'] ?? $shopifyData;
        $billingAddress = $shopifyData['billing_address'] ?? $customer['default_address'] ?? [];

        return [
            'nombre' => $customer['first_name'] ?? '',
            'apellido' => $customer['last_name'] ?? '',
            'nombre_empresa' => $billingAddress['company'] ?? '',
            'telefono' => $billingAddress['phone'] ?? $customer['phone'] ?? '',
            'correo' => $customer['email'] ?? '',
            'direccion' => $billingAddress['address1'] ?? '',
            'pais' => $billingAddress['country'] ?? '',
            'cod_pais' => $billingAddress['country_code'] ?? '',
            'tipo' => 'Persona',
            'empresa_telefono' => $billingAddress['phone'] ?? $customer['phone'] ?? '',
            'empresa_direccion' => $billingAddress['address1'] ?? '',
            'enable' => 1,
            'id_empresa' => $shopifyData['id_empresa'],
            'id_usuario' => $shopifyData['id_usuario'],
        ];
    }

    public function transformarVenta($shopifyData, $clienteId, $documentoId, $correlativo)
    {
        $estado = $this->mapearEstado($shopifyData['financial_status'] ?? 'pending');

        return [
            'codigo_generacion' => null,
            'estado' => $estado,
            'forma_pago' => $this->mapearFormaPago($shopifyData),
            'observaciones' => $shopifyData['note'] ?? '',
            'fecha' => $shopifyData['created_at'],
            'fecha_pago' => $shopifyData['processed_at'] ?? $shopifyData['created_at'],
            'total_costo' => 0,
            'total' => $shopifyData['total_price'],
            'sub_total' => $shopifyData['subtotal_price'],
            'gravada' => $shopifyData['total_price'] - $shopifyData['total_tax'],
            'cuenta_a_terceros' => 0,
            'iva' => $shopifyData['total_tax'],
            'iva_retenido' => 0,
            'iva_percibido' => 0,
            'descuento' => $shopifyData['total_discounts'],
            'id_cliente' => $clienteId,
            'correlativo' => $correlativo,
            'id_documento' => $documentoId,
            'id_bodega' => $shopifyData['id_bodega'],
            'id_empresa' => $shopifyData['id_empresa'],
            'id_usuario' => $shopifyData['id_usuario'],
            'id_sucursal' => $shopifyData['id_sucursal'],
            'id_canal' => $shopifyData['id_canal'],
        ];
    }

    public function transformarDetallesVenta($lineItem, $ventaId)
    {
        return [
            'cantidad' => $lineItem['quantity'],
            'costo' => 0,
            'precio' => $lineItem['price'],
            'total' => $lineItem['quantity'] * $lineItem['price'],
            'total_costo' => 0,
            'descuento' => 0, // Shopify maneja descuentos a nivel de orden
            'no_sujeta' => 0,
            'exenta' => 0,
            'cuenta_a_terceros' => 0,
            'subtotal' => $lineItem['quantity'] * $lineItem['price'],
            'gravada' => $lineItem['quantity'] * $lineItem['price'],
            'iva' => 0, // Calcular según impuestos
            'descripcion' => $lineItem['title'],
            'id_producto' => null,
            'id_venta' => $ventaId
        ];
    }

    public function actualizarInventario($productoId, $cantidad, $bodegaId)
    {
        return [
            'id_producto' => $productoId,
            'id_bodega' => $bodegaId,
            'stock' => ['decrement' => $cantidad],
            'updated_at' => now()
        ];
    }

    public function transformarProducto($shopifyData, $id_empresa, $id_usuario, $id_sucursal)
    {
        return [
            'codigo' => $shopifyData['sku'] ?? '',
            'barcode' => $shopifyData['sku'] ?? '',
            'nombre' => $shopifyData['title'],
            'descripcion' => $shopifyData['product']['body_html'] ?? '',
            'id_empresa' => $id_empresa,
            'id_usuario' => $id_usuario,
            'id_sucursal' => $id_sucursal,
            'costo' => $shopifyData['price'],
            'precio' => $shopifyData['price'],
            'shopify_id' => $shopifyData['id'],
            'shopify_product_id' => $shopifyData['product_id'],
        ];
    }

    private function mapearEstado($shopifyStatus)
    {
        return 'Pendiente';
        $mapeo = [
            'pending' => 'Pendiente',
            'authorized' => 'Pendiente',
            'partially_paid' => 'Pendiente',
            'paid' => 'Pagada',
            'partially_refunded' => 'Pagada',
            'refunded' => 'Reembolsada',
            'voided' => 'Anulada'
        ];

        return $mapeo[$shopifyStatus] ?? 'Pendiente';
    }

    private function mapearFormaPago($shopifyData)
    {
        $gateway = $shopifyData['gateway'] ?? 'unknown';

        $mapeo = [
            'shopify_payments' => 'Tarjeta de crédito/débito',
            'paypal' => 'PayPal',
            'manual' => 'Manual',
            'cash_on_delivery' => 'Contra entrega',
            'bank_transfer' => 'Transferencia bancaria'
        ];

        return $mapeo[$gateway] ?? 'Tarjeta de crédito/débito';
    }

    public function transformarProductoDesdeShopify($shopifyData, $id_empresa, $id_usuario, $id_sucursal)
    {
        return [
            'codigo' => $shopifyData['variants'][0]['sku'] ?? '',
            'barcode' => $shopifyData['variants'][0]['barcode'] ?? '',
            'nombre' => $shopifyData['title'],
            'descripcion' => strip_tags($shopifyData['body_html'] ?? ''),
            'id_empresa' => $id_empresa,
            'id_usuario' => $id_usuario,
            'id_sucursal' => $id_sucursal,
            'precio' => $shopifyData['variants'][0]['price'] ?? 0,
            'shopify_product_id' => $shopifyData['id'],
            'shopify_variant_id' => $shopifyData['variants'][0]['id'],
            'shopify_inventory_item_id' => $shopifyData['variants'][0]['inventory_item_id'],
            'enable' => 1,
            'tipo' => 'Producto',
            'costo' => $shopifyData['variants'][0]['price'] ?? 0,
            'stock' => $shopifyData['variants'][0]['inventory_quantity'] ?? 0,
        ];
    }
}
