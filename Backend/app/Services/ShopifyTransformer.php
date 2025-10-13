<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
            'fecha' => date('Y-m-d', strtotime($shopifyData['created_at'])),
            'fecha_pago' => date('Y-m-d', strtotime($shopifyData['processed_at'] ?? $shopifyData['created_at'])),
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
            'num_orden' => $shopifyData['order_number'] ?? $shopifyData['name'] ?? null,
            'referencia_shopify' => 'SHOPIFY-' . $shopifyData['id'],
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
        $nombreProducto = $this->construirNombreConVariante($shopifyData['title'], $shopifyData);
        
        return [
            'codigo' => $shopifyData['sku'] ?? '',
            'barcode' => $shopifyData['sku'] ?? '',
            'nombre' => $nombreProducto,
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
        // Shopify envía payment_gateway_names como array
        $paymentGateways = $shopifyData['payment_gateway_names'] ?? [];
        $gateway = !empty($paymentGateways) ? $paymentGateways[0] : 'unknown';

        Log::info('Mapeando forma de pago', [
            'payment_gateway_names' => $paymentGateways,
            'gateway_selected' => $gateway
        ]);

        $mapeo = [
            'shopify_payments' => 'Tarjeta de crédito/débito',
            'paypal' => 'PayPal',
            'manual' => 'Manual',
            'Cash on Delivery (COD)' => 'Contra entrega',
            'bank_transfer' => 'Transferencia bancaria',
            'Bank Transfer' => 'Transferencia bancaria',
            'Bank Deposit' => 'Transferencia bancaria',
            'stripe' => 'Tarjeta de crédito/débito',
            'square' => 'Tarjeta de crédito/débito'
        ];

        $formaPago = $mapeo[$gateway] ?? 'Tarjeta de crédito/débito';
        
        Log::info('Forma de pago mapeada', [
            'gateway' => $gateway,
            'forma_pago' => $formaPago
        ]);

        return $formaPago;
    }

    public function transformarProductoDesdeShopify($shopifyData, $id_empresa, $id_usuario, $id_sucursal)
    {
        Log::info("Producto desde Shopify", ['product_id' => $shopifyData['id']]);
        Log::info("Producto desde Shopify", ['product_id' => $shopifyData]);
        
        // Verificar que existan variants
        if (empty($shopifyData['variants']) || !is_array($shopifyData['variants'])) {
            Log::warning("Producto sin variants válidos", ['product_id' => $shopifyData['id']]);
            return [];
        }
        
        $productos = [];
        foreach ($shopifyData['variants'] as $variant) {
            // Verificar que el variant tenga los datos mínimos necesarios
            if (empty($variant['id'])) {
                Log::warning("Variant sin ID válido", ['variant' => $variant]);
                continue;
            }
            
            $nombreProducto = $this->construirNombreConVariante($shopifyData['title'] ?? 'Producto sin nombre', $variant);
            
            $productos[] = [
                'codigo' => $variant['sku'] ?? '',
                'barcode' => $variant['barcode'] ?? '',
                'nombre' => $nombreProducto,
                'descripcion' => strip_tags($shopifyData['body_html'] ?? ''),
                'id_empresa' => $id_empresa,
                'precio' => floatval($variant['price'] ?? 0),
                'shopify_product_id' => $shopifyData['id'],
                'shopify_variant_id' => $variant['id'],
                'shopify_inventory_item_id' => $variant['inventory_item_id'] ?? null,
                'enable' => 1,
                'tipo' => 'Producto',
                'costo' => floatval($variant['price'] ?? 0),
                // Campos de control para prevenir ciclos
                'syncing_from_shopify' => true,
                'last_shopify_sync' => now(),
                // Datos adicionales para el procesamiento (no van al modelo directamente)
                '_stock' => intval($variant['inventory_quantity'] ?? 0),
                '_id_usuario' => $id_usuario,
                '_id_sucursal' => $id_sucursal,
            ];
        }
        return $productos;
    }

    //     return [
    //         'codigo' => $shopifyData['variants'][0]['sku'] ?? '',
    //         'barcode' => $shopifyData['variants'][0]['barcode'] ?? '',
    //         'nombre' => $shopifyData['title'],
    //         'descripcion' => strip_tags($shopifyData['body_html'] ?? ''),
    //         'id_empresa' => $id_empresa,
    //         'id_usuario' => $id_usuario,
    //         'id_sucursal' => $id_sucursal,
    //         'precio' => $shopifyData['variants'][0]['price'] ?? 0,
    //         'shopify_product_id' => $shopifyData['id'],
    //         'shopify_variant_id' => $shopifyData['variants'][0]['id'],
    //         'shopify_inventory_item_id' => $shopifyData['variants'][0]['inventory_item_id'],
    //         'enable' => 1,
    //         'tipo' => 'Producto',
    //         'costo' => $shopifyData['variants'][0]['price'] ?? 0,
    //         'stock' => $shopifyData['variants'][0]['inventory_quantity'] ?? 0,
    //     ];

    public function transformarCategoriaDesdeShopify($shopifyData, $id_empresa)
    {
        // Verificar si la categoría existe en los datos de Shopify
        if (empty($shopifyData['category']) || !isset($shopifyData['category']['name'])) {
            return [
                'nombre' => 'General',
                'descripcion' => 'Categoría general para productos sin categoría específica',
                'id_empresa' => $id_empresa,
                'enable' => 1,
            ];
        }

        return [
            'nombre' => $shopifyData['category']['name'],
            'descripcion' => $shopifyData['category']['full_name'] ?? $shopifyData['category']['name'],
            'id_empresa' => $id_empresa,
            'enable' => 1,
        ];
    }

    /**
     * Construye el nombre del producto incluyendo las opciones de la variante
     * 
     * @param string $tituloProducto Título base del producto
     * @param array $variant Datos de la variante de Shopify
     * @return string Nombre completo del producto con variante
     */
    private function construirNombreConVariante($tituloProducto, $variant)
    {
        $opciones = [];
        
        // Agregar option1 si existe y no está vacío
        if (!empty($variant['option1'])) {
            $opciones[] = $variant['option1'];
        }
        
        // Agregar option2 si existe y no está vacío
        if (!empty($variant['option2'])) {
            $opciones[] = $variant['option2'];
        }
        
        // Agregar option3 si existe y no está vacío
        if (!empty($variant['option3'])) {
            $opciones[] = $variant['option3'];
        }
        
        // Si hay opciones, agregarlas al título
        if (!empty($opciones)) {
            $opcionesTexto = implode(' - ', $opciones);
            $nombreCompleto = $tituloProducto . ' (' . $opcionesTexto . ')';
            
            Log::info("Nombre de producto con variante construido", [
                'titulo_original' => $tituloProducto,
                'opciones' => $opciones,
                'nombre_final' => $nombreCompleto
            ]);
            
            return $nombreCompleto;
        }
        
        // Si no hay opciones, devolver solo el título
        Log::info("Producto sin opciones de variante", [
            'titulo' => $tituloProducto,
            'variant_id' => $variant['id'] ?? 'N/A'
        ]);
        
        return $tituloProducto;
    }
}
