<?php

namespace App\Services;

use App\Constants\ShopifyConstant;
use App\Models\Admin\Empresa;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ShopifyTransformer
{
    protected $impuestosService;

    public function __construct(ImpuestosService $impuestosService)
    {
        $this->impuestosService = $impuestosService;
    }

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

        Log::info('=== TRANSFORMANDO CLIENTE DESDE SHOPIFY (PEDIDO) ===', [
            'shopify_customer_id' => $customer['id'] ?? 'N/A',
            'customer_email' => $customer['email'] ?? 'N/A',
            'customer_name' => ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'default_address' => $defaultAddress,
            'primary_address_used' => $primaryAddress,
            'address_source' => $this->determinarFuenteDireccion($billingAddress, $shippingAddress, $defaultAddress),
            'direccion_completa' => $direccionCompleta,
            'shopify_order_id' => $shopifyData['id'] ?? 'N/A'
        ]);

        $clienteData = [
            'nombre' => $customer['first_name'] ?? '',
            'apellido' => $customer['last_name'] ?? '',
            'nombre_empresa' => $primaryAddress['company'] ?? '',
            'telefono' => $primaryAddress['phone'] ?? $customer['phone'] ?? '',
            'correo' => $customer['email'] ?? '',
            'shopify_customer_id' => $customer['id'] ?? null,
            'direccion' => $direccionCompleta,
            'pais' => $primaryAddress['country'] ?? '',
            'cod_pais' => substr($primaryAddress['country_code'] ?? '', 0, 255),
            'municipio' => $primaryAddress['city'] ?? '',
            'departamento' => $primaryAddress['province'] ?? '',
            'cod_municipio' => substr($primaryAddress['city'] ?? '', 0, 10),
            'cod_departamento' => $this->obtenerCodigoDepartamento($primaryAddress['province_code'] ?? '', $shopifyData['id_empresa'] ?? null),
            'tipo' => 'Persona',
            'empresa_telefono' => $primaryAddress['phone'] ?? $customer['phone'] ?? '',
            'empresa_direccion' => $direccionCompleta,
            'enable' => 1,
            'id_empresa' => $shopifyData['id_empresa'],
            'id_usuario' => $shopifyData['id_usuario'],
        ];

        Log::info('=== DATOS DEL CLIENTE TRANSFORMADOS (PEDIDO) ===', [
            'cliente_data' => $clienteData,
            'shopify_customer_id' => $customer['id'] ?? 'N/A',
            'shopify_order_id' => $shopifyData['id'] ?? 'N/A'
        ]);

        return $clienteData;
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

        Log::info('=== TRANSFORMANDO CLIENTE DESDE WEBHOOK SHOPIFY ===', [
            'shopify_customer_id' => $customer['id'] ?? 'N/A',
            'customer_email' => $customer['email'] ?? 'N/A',
            'customer_name' => ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
            'default_address' => $defaultAddress,
            'direccion_completa' => $direccionCompleta,
            'webhook_type' => 'customers/create o customers/update'
        ]);

        $clienteData = [
            'nombre' => $customer['first_name'] ?? '',
            'apellido' => $customer['last_name'] ?? '',
            'nombre_empresa' => $defaultAddress['company'] ?? '',
            'telefono' => $defaultAddress['phone'] ?? $customer['phone'] ?? '',
            'correo' => $customer['email'] ?? '',
            'shopify_customer_id' => $customer['id'] ?? null,
            'direccion' => $direccionCompleta,
            'pais' => $defaultAddress['country'] ?? '',
            'cod_pais' => substr($defaultAddress['country_code'] ?? '', 0, 255),
            'municipio' => $defaultAddress['city'] ?? '',
            'departamento' => $defaultAddress['province'] ?? '',
            'cod_municipio' => substr($defaultAddress['city'] ?? '', 0, 10),
            'cod_departamento' => $this->obtenerCodigoDepartamento($defaultAddress['province_code'] ?? '', $shopifyData['id_empresa'] ?? null),
            'tipo' => 'Persona',
            'empresa_telefono' => $defaultAddress['phone'] ?? $customer['phone'] ?? '',
            'empresa_direccion' => $direccionCompleta,
            'enable' => 1,
            'id_empresa' => $shopifyData['id_empresa'],
            'id_usuario' => $shopifyData['id_usuario'],
        ];

        Log::info('=== DATOS DEL CLIENTE TRANSFORMADOS (WEBHOOK) ===', [
            'cliente_data' => $clienteData,
            'shopify_customer_id' => $customer['id'] ?? 'N/A'
        ]);

        return $clienteData;
    }

    public function transformarVenta($shopifyData, $clienteId, $documentoId, $correlativo)
    {
        $estado = $this->mapearEstado($shopifyData['financial_status'] ?? $shopifyData['status'] ?? 'pending');
        $empresaId = $shopifyData['id_empresa'];

        $shippingLines = $shopifyData['shipping_lines'] ?? [];
        if (empty($shippingLines) && !empty($shopifyData['shipping_line'])) {
            $shippingLines = [$shopifyData['shipping_line']];
        }

        $taxesIncluded = $shopifyData['taxes_included'] ?? false;

        if ($taxesIncluded) {
            $totalAPagar       = floatval($shopifyData['current_total_price'] ?? $shopifyData['total_price'] ?? 0);
            $totalIVA          = floatval($shopifyData['current_total_tax'] ?? $shopifyData['total_tax'] ?? 0);
            $totalEnvio        = floatval(Arr::get($shopifyData, 'total_shipping_price_set.shop_money.amount', 0));
            $subtotalProductos = floatval($shopifyData['current_subtotal_price'] ?? $shopifyData['subtotal_price'] ?? ($totalAPagar - $totalEnvio));

            $totalGravada = round($subtotalProductos - $totalIVA, 2); // 233.63
            $totalExenta  = $totalEnvio;                               // 3.00

            Log::info("Totales venta (taxes_included=true, desde Shopify)", [
                'current_total_price'    => $totalAPagar,
                'current_total_tax'      => $totalIVA,
                'current_subtotal_price' => $subtotalProductos,
                'total_envio'            => $totalEnvio,
                'gravada'                => $totalGravada,
                'exenta'                 => $totalExenta,
                'empresa_id'             => $empresaId,
            ]);
        } else {
            // Precios sin IVA: calcular línea por línea
            $totalGravada = 0.0;
            $totalIVA = 0.0;

            foreach ($shopifyData['line_items'] ?? [] as $item) {
                $precio = floatval($item['price'] ?? 0);
                $cantidad = floatval($item['quantity'] ?? 0);
                $descuentoTotal = 0;
                foreach ($item['discount_allocations'] ?? [] as $da) {
                    $descuentoTotal += floatval($da['amount'] ?? 0);
                }
                $montoBase = ($precio * $cantidad) - $descuentoTotal;
                $subtotal = round($montoBase, 2);
                $iva = $empresaId
                    ? $this->impuestosService->calcularIvaDesdeBaseGravada($subtotal, $empresaId)
                    : round($subtotal * 0.13, 2);
                $totalGravada += $subtotal;
                $totalIVA += $iva;
            }

            foreach ($shippingLines as $shipping) {
                $precio = floatval($shipping['discounted_price'] ?? $shipping['price'] ?? 0);
                if ($precio > 0) {
                    $subtotal = round($precio, 2);
                    $iva = $empresaId
                        ? $this->impuestosService->calcularIvaDesdeBaseGravada($subtotal, $empresaId)
                        : round($subtotal * 0.13, 2);
                    $totalGravada += $subtotal;
                    $totalIVA += $iva;
                }
            }

            $totalGravada = round($totalGravada, 2);
            $totalIVA = round($totalIVA, 2);
            $totalAPagar = $totalGravada + $totalIVA;

            $totalExenta = 0;

            Log::info("Totales venta (taxes_included=false, suma de líneas)", [
                'total_gravada' => $totalGravada,
                'total_iva' => $totalIVA,
                'total_a_pagar' => $totalAPagar,
                'empresa_id' => $empresaId,
            ]);
        }

        return [
            'codigo_generacion' => null,
            'estado' => $estado,
            'forma_pago' => $this->mapearFormaPago($shopifyData),
            'observaciones' => $shopifyData['note'] ?? '',
            'fecha' => date('Y-m-d', strtotime($shopifyData['created_at'])),
            'fecha_pago' => date('Y-m-d', strtotime($shopifyData['processed_at'] ?? $shopifyData['created_at'])),
            'total_costo' => 0,
            'total' => $totalAPagar,
            'sub_total' => $totalGravada,
            'gravada' => $totalGravada,
            'exenta' => $totalExenta,
            'cuenta_a_terceros' => 0,
            'iva' => $totalIVA,
            'iva_retenido' => 0,
            'iva_percibido' => 0,
            'descuento' => 0.00,
            'monto_pago' => $totalAPagar,
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

    /**
     * Calcula subtotal, IVA y total de línea según reglas de Hacienda (El Salvador).
     * Prioridad aritmética: subtotal = round(TotalConIva/1.13, 2), iva = round(subtotal*0.13, 2), total = subtotal + iva.
     *
     * @param float $totalLineaConIVA Monto total de la línea con IVA incluido
     * @param int|null $empresaId
     * @return array{0: float, 1: float, 2: float} [subtotal, iva, total]
     */
    private function calcularValoresLineaConIva($totalLineaConIVA, $empresaId = null)
    {
        $totalLineaConIVA = floatval($totalLineaConIVA);

        if ($totalLineaConIVA <= 0) {
            return [0.0, 0.0, 0.0];
        }

        $porcentajeIva = $empresaId ? $this->impuestosService->obtenerPorcentajeImpuesto($empresaId) : 13.0;

        if ($porcentajeIva <= 0) {
            return [round($totalLineaConIVA, 2), 0.0, round($totalLineaConIVA, 2)];
        }

        $factor = 1 + ($porcentajeIva / 100);
        $subtotal = round($totalLineaConIVA / $factor, 2);
        $iva = $empresaId
            ? $this->impuestosService->calcularIvaDesdeBaseGravada($subtotal, $empresaId)
            : round($subtotal * ($porcentajeIva / 100), 2);
        $total = $subtotal + $iva;

        return [$subtotal, $iva, $total];
    }

    public function transformarDetallesVenta($lineItem, $ventaId, $empresaId = null, $taxesIncluded = false)
    {
        $precio = floatval($lineItem['price']);
        $cantidad = floatval($lineItem['quantity'] ?? 1);

        $descuentoTotal = 0;
        foreach ($lineItem['discount_allocations'] ?? [] as $da) {
            $descuentoTotal += floatval($da['amount'] ?? 0);
        }

        $totalConIva = ($precio * $cantidad) - $descuentoTotal;

        if ($taxesIncluded) {
            // Precios incluyen IVA: usar tax_lines de Shopify como IVA de la línea (ya calculado por Shopify)
            $ivaLinea = 0.0;
            foreach ($lineItem['tax_lines'] ?? [] as $tl) {
                $ivaLinea += floatval($tl['price'] ?? 0);
            }
            if ($ivaLinea <= 0) {
                // Fallback si no hay tax_lines: derivar IVA desde totalConIva
                $subtotalTmp = round($totalConIva / 1.13, 2);
                $ivaLinea = $empresaId
                    ? $this->impuestosService->calcularIvaDesdeBaseGravada($subtotalTmp, $empresaId)
                    : round($subtotalTmp * 0.13, 2);
            }
            $subtotalLinea = round($totalConIva - $ivaLinea, 2);
            $totalLinea = $subtotalLinea + $ivaLinea;
        } else {
            // Precios NO incluyen IVA: totalConIva es en realidad la base gravada
            $subtotalLinea = round($totalConIva, 2);
            $ivaLinea = $empresaId
                ? $this->impuestosService->calcularIvaDesdeBaseGravada($subtotalLinea, $empresaId)
                : round($subtotalLinea * 0.13, 2);
            $totalLinea = $subtotalLinea + $ivaLinea;
        }

        $precioSinImpuesto = $cantidad > 0 ? round($subtotalLinea / $cantidad, 4) : 0;

        Log::info("Procesando línea de item (reglas Hacienda)", [
            'line_item_title' => $lineItem['title'] ?? 'N/A',
            'taxes_included' => $taxesIncluded,
            'total_con_iva' => $totalConIva,
            'subtotal_linea' => $subtotalLinea,
            'iva_linea' => $ivaLinea,
            'total_recalculado' => $totalLinea,
            'verificacion' => $subtotalLinea + $ivaLinea,
        ]);

        return [
            'cantidad' => $lineItem['quantity'],
            'costo' => 0,
            'precio' => $precioSinImpuesto,
            'precio_sin_iva' => $precioSinImpuesto,
            'precio_con_iva' => $cantidad > 0 ? round($totalLinea / $cantidad, 2) : $precio,
            'total' => $subtotalLinea,
            'total_costo' => 0,
            'descuento' => 0.00,
            'no_sujeta' => 0,
            'exenta' => 0,
            'cuenta_a_terceros' => 0,
            'subtotal' => $subtotalLinea,
            'gravada' => $subtotalLinea,
            'iva' => $ivaLinea,
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

        // Obtener categoría "Uncategorized" por defecto para productos personalizados
        $categoriaUncategorized = \App\Models\Inventario\Categorias\Categoria::where('id_empresa', $id_empresa)
            ->where('nombre', 'Uncategorized')
            ->first();

        // Procesar descripción: limitar a 100 caracteres para descripcion, completa para descripcion_completa
        $descripcionCompleta = $shopifyData['product']['body_html'] ?? '';
        $descripcionCorta = mb_substr($descripcionCompleta, 0, 100);

        return [
            'codigo' => $shopifyData['sku'] ?? '',
            'barcode' => $shopifyData['sku'] ?? '',
            'nombre' => $nombreBase,
            'nombre_variante' => $nombreVariante,
            'descripcion' => $descripcionCorta,
            'descripcion_completa' => $descripcionCompleta,
            'id_empresa' => $id_empresa,
            'id_usuario' => $id_usuario,
            'id_sucursal' => $id_sucursal,
            'id_categoria' => $categoriaUncategorized ? $categoriaUncategorized->id : null,
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
            'refunded' => 'Anulada', // Cambiado de 'Reembolsada' a 'Anulada'
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
        $paymentGateways = $shopifyData['payment_gateway_names'] ?? [];
        $gateway = !empty($paymentGateways) ? $paymentGateways[0] : 'unknown';
        $financialStatus = $shopifyData['financial_status'] ?? $shopifyData['status'] ?? 'pending';

        // Normalizar solo para búsqueda (no modifica el original)
        $gatewayLower = strtolower(trim($gateway));

        // Mapeo en minúsculas (más simple y seguro)
        $mapeo = [
            'shopify_payments' => 'Tarjeta de crédito/débito',
            'paypal' => 'PayPal',
            'paypal_express' => 'PayPal',
            'paypal_express_checkout' => 'PayPal',
            'kueski_pay' => 'KueskiPay',
            'kueskipay' => 'KueskiPay',
            'kueski pay' => 'KueskiPay',
            'manual' => 'Manual',
            'cash' => 'Efectivo',
            'efectivo' => 'Efectivo',
            'cash on delivery (cod)' => 'Contra entrega',
            'pago contra entrega' => 'Contra entrega',
            'bank_transfer' => 'Transferencia bancaria',
            'bank deposit' => 'Transferencia bancaria',
            'depósito bancario' => 'Transferencia bancaria',
            'stripe' => 'Tarjeta de crédito/débito',
            'square' => 'Tarjeta de crédito/débito',
            'wompi el salvador' => 'Wompi',
        ];

        // Buscar usando la versión normalizada
        $formaPago = $mapeo[$gatewayLower] ?? null;

        // Fallbacks inteligentes (solo si no encontró coincidencia)
        if (!$formaPago) {
            if (str_contains($gatewayLower, 'paypal')) {
                $formaPago = 'PayPal';
            } elseif (str_contains($gatewayLower, 'kueski')) {
                $formaPago = 'KueskiPay';
            } elseif (str_contains($gatewayLower, 'wompi')) {
                $formaPago = 'Wompi';
            }
        }

        // Default final
        $formaPago = $formaPago ?? 'Tarjeta de crédito/débito';

        // Caso especial: Manual + Pagado = Wompi
        if ($gatewayLower === 'manual' && in_array($financialStatus, ['paid', 'partially_paid'])) {
            $formaPago = 'Wompi';
        }

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
                'precio' => $this->impuestosService->calcularPrecioSinImpuesto($variant['price'] ?? 0, $id_empresa),
                'costo_original' => $variant['cost'] ?? 'no_existe',
                'costo_asignado' => $costo,
                'status_shopify' => $status,
                'sera_activo' => $productoActivo == 1
            ]);

            $precioConIva = floatval($variant['price'] ?? 0);
            $precioSinIva = $this->impuestosService->calcularPrecioSinImpuesto($precioConIva, $id_empresa);

            // Procesar descripción: limitar a 100 caracteres para descripcion, completa para descripcion_completa
            $descripcionCompleta = strip_tags($shopifyData['body_html'] ?? '');
            $descripcionCorta = mb_substr($descripcionCompleta, 0, 100);

            $productos[] = [
                'codigo' => $variant['sku'] ?? '',
                'barcode' => $variant['barcode'] ?? '',
                'nombre' => $nombreBase,
                'nombre_variante' => $nombreVariante,
                'descripcion' => $descripcionCorta,
                'descripcion_completa' => $descripcionCompleta,
                'id_empresa' => $id_empresa,
                'precio' => $precioSinIva,
                'precio_sin_iva' => $precioSinIva,
                'precio_con_iva' => $precioConIva,
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
     * Calcula el subtotal sin impuesto sumando los subtotales de línea ya redondeados.
     * Usa la misma lógica que transformarDetallesVenta: TotalLineaConIVA / 1.13 redondeado por línea.
     * Garantiza que el encabezado de la venta coincida con la suma de sus detalles.
     */
    private function calcularSubtotalSinImpuesto($lineItems, $shippingLines = [], $empresaId = null)
    {
        $subtotalSinImpuesto = 0;

        // Calcular subtotal de productos: TotalLineaConIVA por línea, luego round(.../1.13, 2)
        foreach ($lineItems as $item) {
            $precioConImpuesto = floatval($item['price'] ?? 0);
            $cantidad = floatval($item['quantity'] ?? 0);

            // Descuentos de la línea
            $descuentoTotal = 0;
            foreach ($item['discount_allocations'] ?? [] as $discountAllocation) {
                $descuentoTotal += floatval($discountAllocation['amount'] ?? 0);
            }

            $totalLineaConIVA = ($precioConImpuesto * $cantidad) - $descuentoTotal;

            if ($empresaId && $totalLineaConIVA > 0) {
                $porcentajeIva = $this->impuestosService->obtenerPorcentajeImpuesto($empresaId);
                if ($porcentajeIva > 0) {
                    $subtotalLinea = round(
                        $this->impuestosService->calcularPrecioSinImpuesto($totalLineaConIVA, $empresaId, false),
                        2
                    );
                } else {
                    $subtotalLinea = round($totalLineaConIVA, 2);
                }
            } else {
                $subtotalLinea = round($totalLineaConIVA / 1.13, 2);
            }
            $subtotalSinImpuesto += $subtotalLinea;
        }

        // Calcular subtotal de envíos (TotalEnvioConIVA / 1.13 redondeado)
        foreach ($shippingLines as $shipping) {
            $precioConImpuesto = floatval($shipping['discounted_price'] ?? $shipping['price'] ?? 0);
            if ($precioConImpuesto > 0) {
                if ($empresaId) {
                    $porcentajeIva = $this->impuestosService->obtenerPorcentajeImpuesto($empresaId);
                    if ($porcentajeIva > 0) {
                        $subtotalEnvio = round(
                            $this->impuestosService->calcularPrecioSinImpuesto($precioConImpuesto, $empresaId, false),
                            2
                        );
                    } else {
                        $subtotalEnvio = round($precioConImpuesto, 2);
                    }
                } else {
                    $subtotalEnvio = round($precioConImpuesto / 1.13, 2);
                }
                $subtotalSinImpuesto += $subtotalEnvio;
            }
        }

        Log::info("Subtotal sin impuesto calculado (suma de subtotales de línea redondeados)", [
            'subtotal_sin_impuesto' => $subtotalSinImpuesto,
            'line_items_count' => count($lineItems),
            'shipping_lines_count' => count($shippingLines),
            'empresa_id' => $empresaId,
        ]);

        return round($subtotalSinImpuesto, 2);
    }

    private function calcularPrecioSinImpuesto($precioConImpuesto, $empresaId = null)
    {
        // Delegar al servicio de impuestos si se proporciona empresaId
        if ($empresaId) {
            return $this->impuestosService->calcularPrecioSinImpuesto($precioConImpuesto, $empresaId);
        }

        // Fallback: usar cálculo hardcodeado para compatibilidad con código antiguo
        $precioConImpuesto = floatval($precioConImpuesto);

        if ($precioConImpuesto <= 0) {
            return 0.0;
        }

        // Usar 13% como fallback (para compatibilidad con código existente)
        $factorSinImpuesto = 1 / 1.13;
        $precioSinImpuesto = $precioConImpuesto * $factorSinImpuesto;

        // Redondear a 2 decimales para evitar problemas de precisión
        $precioSinImpuesto = round($precioSinImpuesto, 2);

        Log::warning("Usando cálculo de impuesto hardcodeado (13%) - considera pasar empresaId", [
            'precio_con_impuesto' => $precioConImpuesto,
            'precio_sin_impuesto' => $precioSinImpuesto,
            'impuesto_calculado' => $precioConImpuesto - $precioSinImpuesto
        ]);

        return $precioSinImpuesto;
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

    //Obtiene el código de departamento mapeado desde Shopify si la facturación electrónica está activada
    private function obtenerCodigoDepartamento($provinceCode, $empresaId = null)
    {
        if (empty($provinceCode)) {
            return '';
        }

        if (!$empresaId) {
            return substr($provinceCode, 0, 10);
        }

        $empresa = Empresa::find($empresaId);
        if (!$empresa || !$empresa->facturacion_electronica) {
            // Si no tiene facturación electrónica, retornar el código original
            return substr($provinceCode, 0, 10);
        }

        // Si tiene facturación electrónica, mapear el código
        $codigoMapeado = $this->mapearCodigoDepartamentoShopify($provinceCode);

        return $codigoMapeado;
    }

 // Se mapearn los codigos de shopify a codigo de FA
    private function mapearCodigoDepartamentoShopify($provinceCode)
    {
        // funcion de ShopifyConstant para obtener el código
        $codigoMapeado = ShopifyConstant::obtenerCodigoDepartamento($provinceCode);

        // Si se encontró el código mapeado, retornarlo
        if ($codigoMapeado !== null) {
            return $codigoMapeado;
        }

        Log::warning('Código de departamento de Shopify no encontrado en el mapeo', [
            'province_code' => $provinceCode,
            'province_code_upper' => strtoupper(trim($provinceCode))
        ]);

        return substr($provinceCode, 0, 10);
    }
}
