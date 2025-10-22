<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ShopifyTransformer
{

    public function transformarCliente($shopifyData)
    {
        $customer = $shopifyData['customer'] ?? $shopifyData;
        $billingAddress = $shopifyData['billing_address'] ?? [];
        $shippingAddress = $shopifyData['shipping_address'] ?? [];
        $defaultAddress = $customer['default_address'] ?? [];

        // Determinar qué dirección usar como principal
        // Prioridad: billing_address > shipping_address > default_address
        $primaryAddress = $billingAddress;
        if (empty($primaryAddress) || empty($primaryAddress['address1'])) {
            $primaryAddress = $shippingAddress;
        }
        if (empty($primaryAddress) || empty($primaryAddress['address1'])) {
            $primaryAddress = $defaultAddress;
        }

        // Construir dirección completa
        $direccionCompleta = trim(($primaryAddress['address1'] ?? '') . ' ' . ($primaryAddress['address2'] ?? ''));

        // Log::info('Transformando cliente desde Shopify', [
        //     'customer_email' => $customer['email'] ?? 'N/A',
        //     'billing_address' => $billingAddress,
        //     'shipping_address' => $shippingAddress,
        //     'default_address' => $defaultAddress,
        //     'primary_address_used' => $primaryAddress,
        //     'address_source' => $this->determinarFuenteDireccion($billingAddress, $shippingAddress, $defaultAddress)
        // ]);

        return [
            'nombre' => $customer['first_name'] ?? '',
            'apellido' => $customer['last_name'] ?? '',
            'nombre_empresa' => $primaryAddress['company'] ?? '',
            'telefono' => $primaryAddress['phone'] ?? $customer['phone'] ?? '',
            'correo' => $customer['email'] ?? '',
            'direccion' => $direccionCompleta,
            'pais' => $primaryAddress['country'] ?? '',
            'cod_pais' => substr($primaryAddress['country_code'] ?? '', 0, 10),
            'municipio' => $primaryAddress['city'] ?? '',
            'departamento' => $primaryAddress['province'] ?? '',
            'cod_municipio' => substr($primaryAddress['city'] ?? '', 0, 50),
            'cod_departamento' => substr($primaryAddress['province_code'] ?? '', 0, 10),
            'tipo' => 'Persona',
            'empresa_telefono' => $primaryAddress['phone'] ?? $customer['phone'] ?? '',
            'empresa_direccion' => $direccionCompleta,
            'enable' => 1,
            'id_empresa' => $shopifyData['id_empresa'],
            'id_usuario' => $shopifyData['id_usuario'],
        ];
    }

    private function determinarFuenteDireccion($billingAddress, $shippingAddress, $defaultAddress)
    {
        if (!empty($billingAddress) && !empty($billingAddress['address1'])) {
            return 'billing_address';
        }
        if (!empty($shippingAddress) && !empty($shippingAddress['address1'])) {
            return 'shipping_address';
        }
        if (!empty($defaultAddress) && !empty($defaultAddress['address1'])) {
            return 'default_address';
        }
        return 'none';
    }

    public function transformarClienteDesdeShopify($shopifyData)
    {
        // Para webhooks de customers/create y customers/update, los datos vienen directamente
        $customer = $shopifyData;
        $defaultAddress = $customer['default_address'] ?? [];

        // Construir dirección completa
        $direccionCompleta = trim(($defaultAddress['address1'] ?? '') . ' ' . ($defaultAddress['address2'] ?? ''));

        // Log::info('Transformando cliente desde webhook de Shopify', [
        //     'shopify_customer_id' => $customer['id'] ?? 'N/A',
        //     'customer_email' => $customer['email'] ?? 'N/A',
        //     'default_address' => $defaultAddress
        // ]);

        return [
            'nombre' => $customer['first_name'] ?? '',
            'apellido' => $customer['last_name'] ?? '',
            'nombre_empresa' => $defaultAddress['company'] ?? '',
            'telefono' => $defaultAddress['phone'] ?? $customer['phone'] ?? '',
            'correo' => $customer['email'] ?? '',
            'direccion' => $direccionCompleta,
            'pais' => $defaultAddress['country'] ?? '',
            'cod_pais' => substr($defaultAddress['country_code'] ?? '', 0, 10),
            'municipio' => $defaultAddress['city'] ?? '',
            'departamento' => $defaultAddress['province'] ?? '',
            'cod_municipio' => substr($defaultAddress['city'] ?? '', 0, 50),
            'cod_departamento' => substr($defaultAddress['province_code'] ?? '', 0, 10),
            'tipo' => 'Persona',
            'empresa_telefono' => $defaultAddress['phone'] ?? $customer['phone'] ?? '',
            'empresa_direccion' => $direccionCompleta,
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
        $nombreBase = $this->obtenerNombreBase($shopifyData['title']);
        $nombreVariante = $this->construirNombreVariante($shopifyData);

        return [
            'codigo' => $shopifyData['sku'] ?? '',
            'barcode' => $shopifyData['sku'] ?? '',
            'nombre' => $nombreBase,
            'nombre_variante' => $nombreVariante,
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
        $mapeo = [
            'pending' => 'Pendiente',
            'authorized' => 'Pendiente',
            'partially_paid' => 'Pendiente',
            'paid' => 'Pagada',
            'partially_refunded' => 'Pagada',
            'refunded' => 'Reembolsada',
            'voided' => 'Anulada'
        ];

        $estado = $mapeo[$shopifyStatus] ?? 'Pendiente';

        // Log::info('Mapeando estado de pago', [
        //     'shopify_status' => $shopifyStatus,
        //     'estado_mapeado' => $estado
        // ]);

        return $estado;
    }

    private function mapearFormaPago($shopifyData)
    {
        // Shopify envía payment_gateway_names como array
        $paymentGateways = $shopifyData['payment_gateway_names'] ?? [];
        $gateway = !empty($paymentGateways) ? $paymentGateways[0] : 'unknown';

        // Log::info('Mapeando forma de pago', [
        //     'payment_gateway_names' => $paymentGateways,
        //     'gateway_selected' => $gateway
        // ]);

        $mapeo = [
            'shopify_payments' => 'Tarjeta de crédito/débito',
            'paypal' => 'PayPal',
            'manual' => 'Manual',
            'Cash on Delivery (COD)' => 'Contra entrega',
            'bank_transfer' => 'Transferencia bancaria',
            'Bank Transfer' => 'Transferencia bancaria',
            'Bank Deposit' => 'Transferencia bancaria',
            'stripe' => 'Tarjeta de crédito/débito',
            'square' => 'Tarjeta de crédito/débito',
            'Wompi El Salvador' => 'Wompi',
        ];

        $formaPago = $mapeo[$gateway] ?? 'Tarjeta de crédito/débito';

        // Log::info('Forma de pago mapeada', [
        //     'gateway' => $gateway,
        //     'forma_pago' => $formaPago
        // ]);

        return $formaPago;
    }

    public function transformarProductoDesdeShopify($shopifyData, $id_empresa, $id_usuario, $id_sucursal, $incluirDrafts = true, $esImportacionMasiva = false)
    {
        // Verificar el status del producto
        $status = $shopifyData['status'] ?? 'unknown';
        
        // Determinar si el producto debe ser activo o inactivo
        $productoActivo = ($status === 'active') ? 1 : 0;
        
        if ($status !== 'active' && $status !== 'draft') {
            Log::info("Producto omitido por status", [
                'product_id' => $shopifyData['id'],
                'status' => $status,
                'titulo' => $shopifyData['title'] ?? 'Sin título'
            ]);
            return [];
        }
        
        if ($status === 'draft') {
            Log::info("Producto draft incluido como inactivo", [
                'product_id' => $shopifyData['id'],
                'status' => $status,
                'titulo' => $shopifyData['title'] ?? 'Sin título',
                'sera_activo' => false
            ]);
        }

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

            $nombreBase = $this->obtenerNombreBase($shopifyData['title'] ?? 'Producto sin nombre');
            $nombreVariante = $this->construirNombreVariante($variant);
            
            // Obtener costo del variant, si no existe usar 0
            $costo = floatval($variant['cost'] ?? 0);
            
            Log::info("Procesando variant para producto", [
                'product_id' => $shopifyData['id'],
                'variant_id' => $variant['id'],
                'nombre_base' => $nombreBase,
                'nombre_variante' => $nombreVariante,
                'precio' => $this->calcularPrecioSinIVA($variant['price'] ?? 0),
                'costo_original' => $variant['cost'] ?? 'no_existe',
                'costo_asignado' => $costo,
                'status_shopify' => $status,
                'sera_activo' => $productoActivo == 1
            ]);

            $productos[] = [
                'codigo' => $variant['sku'] ?? '',
                'barcode' => $variant['barcode'] ?? '',
                'nombre' => $nombreBase,
                'nombre_variante' => $nombreVariante,
                'descripcion' => strip_tags($shopifyData['body_html'] ?? ''),
                'id_empresa' => $id_empresa,
                'precio' => $this->calcularPrecioSinIVA($variant['price'] ?? 0),
                'shopify_product_id' => $shopifyData['id'],
                'shopify_variant_id' => $variant['id'],
                'shopify_inventory_item_id' => $variant['inventory_item_id'] ?? null,
                'enable' => $productoActivo,
                'tipo' => 'Producto',
                'costo' => $costo,
                // Campos de control para prevenir ciclos - solo para importaciones masivas
                'syncing_from_shopify' => $esImportacionMasiva,
                'last_shopify_sync' => now(),
                // Datos adicionales para el procesamiento (no van al modelo directamente)
                '_stock' => intval($variant['inventory_quantity'] ?? 0),
                '_id_usuario' => $id_usuario,
                '_id_sucursal' => $id_sucursal,
                'imagen_url' => $this->obtenerPrimeraImagen($shopifyData),
                'shopify_image_id' => $this->obtenerPrimeraImagenId($shopifyData),
            ];
        }
        return $productos;
    }

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
     * Obtiene el nombre base del producto sin variantes
     */
    private function obtenerNombreBase($tituloProducto)
    {
        return $this->limpiarTituloDefault($tituloProducto);
    }

    /**
     * Construye el nombre de la variante basado en las opciones del variant
     */
    private function construirNombreVariante($variant)
    {
        $opciones = [];

        // Agregar option1 si existe, no está vacío y no es "Default Title"
        if (!empty($variant['option1']) && $variant['option1'] !== 'Default Title') {
            $opciones[] = $variant['option1'];
        }

        // Agregar option2 si existe, no está vacío y no es "Default Title"
        if (!empty($variant['option2']) && $variant['option2'] !== 'Default Title') {
            $opciones[] = $variant['option2'];
        }

        // Agregar option3 si existe, no está vacío y no es "Default Title"
        if (!empty($variant['option3']) && $variant['option3'] !== 'Default Title') {
            $opciones[] = $variant['option3'];
        }

        // Si hay opciones reales, devolverlas como string
        if (!empty($opciones)) {
            $nombreVariante = implode(' - ', $opciones);
            
            Log::info("Nombre de variante construido", [
                'opciones' => $opciones,
                'nombre_variante' => $nombreVariante,
                'variant_id' => $variant['id'] ?? 'N/A'
            ]);

            return $nombreVariante;
        }

        // Si no hay opciones reales, devolver null
        Log::info("Variante sin opciones específicas", [
            'variant_id' => $variant['id'] ?? 'N/A',
            'option1' => $variant['option1'] ?? 'N/A',
            'option2' => $variant['option2'] ?? 'N/A',
            'option3' => $variant['option3'] ?? 'N/A'
        ]);

        return null;
    }

    /**
     * Limpia el título del producto removiendo "(Default Title)" cuando no hay variantes
     */
    private function limpiarTituloDefault($titulo)
    {
        // Remover "(Default Title)" del final del título
        $tituloLimpio = preg_replace('/\s*\(Default Title\)\s*$/i', '', $titulo);
        
        // También remover variaciones comunes como "(Default)" o "(Default Variant)"
        $tituloLimpio = preg_replace('/\s*\(Default(?: Variant)?\)\s*$/i', '', $tituloLimpio);
        
        // Limpiar espacios extra al final
        $tituloLimpio = trim($tituloLimpio);
        
        return $tituloLimpio;
    }

    /**
     * Calcula el precio sin IVA desde el precio con IVA de Shopify
     * 
     * @param float|string $precioConIVA Precio con IVA desde Shopify
     * @return float Precio sin IVA para SmartPyme
     */
    private function calcularPrecioSinIVA($precioConIVA)
    {
        $precioConIVA = floatval($precioConIVA);
        
        if ($precioConIVA <= 0) {
            return 0.0;
        }
        
        // Calcular precio sin IVA usando factor más preciso
        // Factor: 1 / 1.13 = 0.8849557522123894
        $factorSinIVA = 1 / 1.13;
        $precioSinIVA = $precioConIVA * $factorSinIVA;
        
        // Redondear a 4 decimales para mayor precisión
        $precioSinIVA = round($precioSinIVA, 4);
        
        // Validar precisión: verificar que el cálculo inverso coincida
        $precioInverso = round($precioSinIVA * 1.13, 2);
        $diferencia = abs($precioConIVA - $precioInverso);
        
        Log::info("Precio calculado sin IVA", [
            'precio_con_iva' => $precioConIVA,
            'precio_sin_iva' => $precioSinIVA,
            'precio_inverso' => $precioInverso,
            'diferencia' => $diferencia,
            'iva_calculado' => $precioConIVA - $precioSinIVA
        ]);
        
        // Si la diferencia es muy pequeña (menos de 1 centavo), usar el precio original
        if ($diferencia < 0.01) {
            Log::info("Diferencia mínima detectada, usando precio original", [
                'precio_original' => $precioConIVA,
                'diferencia' => $diferencia
            ]);
        }
        
        return $precioSinIVA;
    }

    private function obtenerPrimeraImagen($shopifyData)
    {
        // Obtener la primera imagen del producto
        if (!empty($shopifyData['images']) && is_array($shopifyData['images'])) {
            $primeraImagen = $shopifyData['images'][0];
            if (isset($primeraImagen['src'])) {
                return $primeraImagen['src'];
            }
        }
        
        // Si no hay imágenes en el array, verificar si hay una imagen directa
        if (isset($shopifyData['image']['src'])) {
            return $shopifyData['image']['src'];
        }
        
        return null;
    }

    private function obtenerPrimeraImagenId($shopifyData)
    {
        // Obtener el ID de la primera imagen del producto
        if (!empty($shopifyData['images']) && is_array($shopifyData['images'])) {
            $primeraImagen = $shopifyData['images'][0];
            if (isset($primeraImagen['id'])) {
                return $primeraImagen['id'];
            }
        }
        
        // Si no hay imágenes en el array, verificar si hay una imagen directa
        if (isset($shopifyData['image']['id'])) {
            return $shopifyData['image']['id'];
        }
        
        return null;
    }
}
