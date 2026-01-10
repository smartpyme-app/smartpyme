<?php

namespace App\Services\Shopify;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Venta;
use App\Services\ShopifyTransformer;
use App\Services\ShippingService;
use App\Services\ImpuestosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShopifyVentaService
{
    protected $transformer;
    protected $shippingService;
    protected $impuestosService;

    public function __construct(
        ShopifyTransformer $transformer,
        ShippingService $shippingService,
        ImpuestosService $impuestosService
    ) {
        $this->transformer = $transformer;
        $this->shippingService = $shippingService;
        $this->impuestosService = $impuestosService;
    }

    /**
     * Mapea el estado financiero de Shopify al estado de SmartPyme
     *
     * @param string $shopifyStatus
     * @return string
     */
    public function mapearEstado(string $shopifyStatus): string
    {
        $mapeo = [
            'pending' => 'Pendiente',
            'authorized' => 'Pendiente',
            'partially_paid' => 'Pendiente',
            'paid' => 'Pagada',
            'partially_refunded' => 'Pagada',
            'refunded' => 'Anulada',
            'voided' => 'Anulada'
        ];

        return $mapeo[$shopifyStatus] ?? 'Pendiente';
    }

    /**
     * Determina si se debe revertir el inventario basado en el webhook de Shopify
     *
     * @param Request $request
     * @return bool
     */
    public function debeRevertirInventario(Request $request): bool
    {
        // 1. Verificar si hay refunds con restock
        if (isset($request->refunds) && is_array($request->refunds)) {
            foreach ($request->refunds as $refund) {
                if (isset($refund['restock']) && $refund['restock'] === true) {
                    Log::info("Inventario debe revertirse - refund con restock encontrado", [
                        'refund_id' => $refund['id'] ?? 'N/A'
                    ]);
                    return true;
                }
            }
        }

        // 2. Verificar el cancel_reason y financial_status
        $cancelReason = $request->input('cancel_reason');
        $financialStatus = $request->input('financial_status');

        // Si el pedido está voided y no hay refunds, generalmente significa que se revierte el inventario
        if ($financialStatus === 'voided' && empty($request->refunds)) {
            Log::info("Inventario debe revertirse - pedido voided sin refunds", [
                'cancel_reason' => $cancelReason,
                'financial_status' => $financialStatus
            ]);
            return true;
        }

        // 3. Verificar si hay line_items con información de restock
        if (isset($request->line_items) && is_array($request->line_items)) {
            foreach ($request->line_items as $lineItem) {
                // Si el line item tiene fulfillable_quantity > 0, significa que no se ha enviado
                // y por tanto se debe revertir el inventario
                if (isset($lineItem['fulfillable_quantity']) && $lineItem['fulfillable_quantity'] > 0) {
                    Log::info("Inventario debe revertirse - line item con fulfillable_quantity > 0", [
                        'line_item_id' => $lineItem['id'] ?? 'N/A',
                        'fulfillable_quantity' => $lineItem['fulfillable_quantity']
                    ]);
                    return true;
                }
            }
        }

        // 4. Por defecto, si no hay información específica, asumir que NO se debe revertir
        // Esto es más seguro para evitar restaurar stock cuando no se debe
        Log::info("No se revierte inventario - no se encontró indicación clara de restock", [
            'cancel_reason' => $cancelReason,
            'financial_status' => $financialStatus,
            'has_refunds' => !empty($request->refunds)
        ]);

        return false;
    }

    /**
     * Recalcula los totales de una venta después de actualizar cantidades
     *
     * @param Venta $venta
     * @return void
     */
    public function recalcularTotalesVenta(Venta $venta): void
    {
        $subtotal = 0;
        $iva = 0;
        $gravada = 0;

        foreach ($venta->detalles as $detalle) {
            $subtotal += round($detalle->cantidad * $detalle->precio, 2);
            $iva += round($detalle->iva, 2);
            $gravada += round($detalle->gravada, 2);
        }

        $total = round($gravada + $iva, 2);

        $venta->update([
            'sub_total' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'gravada' => round($gravada, 2),
            'total' => $total
        ]);

        Log::info("Totales de venta recalculados", [
            'venta_id' => $venta->id,
            'referencia_shopify' => $venta->referencia_shopify,
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'gravada' => round($gravada, 2),
            'total' => $total,
            'es_venta_shopify' => !empty($venta->referencia_shopify)
        ]);
    }

    /**
     * Actualiza las cantidades de productos en una venta existente
     *
     * @param Venta $venta
     * @param Request $request
     * @param User $usuario
     * @return void
     */
    public function actualizarCantidadesProductos(Venta $venta, Request $request, User $usuario): void
    {
        Log::info("Iniciando actualización de cantidades de productos", [
            'venta_id' => $venta->id,
            'shopify_order_id' => $request->id,
            'line_items_count' => count($request->line_items ?? [])
        ]);

        $lineItems = $request->line_items ?? [];

        // Crear un mapa de variant_ids a current_quantity para búsqueda O(1) en lugar de O(n)
        $variantIdsMap = [];
        foreach ($lineItems as $item) {
            $variantId = $item['variant_id'] ?? null;
            if ($variantId) {
                $variantIdsMap[$variantId] = $item['current_quantity'] ?? $item['quantity'] ?? 0;
            }
        }

        // Obtener todos los detalles de venta que son productos de Shopify (tienen shopify_variant_id)
        // Esto excluye automáticamente los servicios de envío que no tienen variant_id
        $detallesExistentes = $venta->detalles()
            ->whereHas('producto', function($query) {
                $query->whereNotNull('shopify_variant_id');
            })
            ->get();

        // Eliminar detalles que ya no están en Shopify o tienen current_quantity = 0
        foreach ($detallesExistentes as $detalle) {
            $producto = $detalle->producto;
            $variantId = $producto->shopify_variant_id ?? null;

            // Buscar en el mapa en lugar de hacer un loop (más eficiente)
            $encontradoEnShopify = isset($variantIdsMap[$variantId]);
            $currentQuantity = $encontradoEnShopify ? $variantIdsMap[$variantId] : 0;

            // Si no está en Shopify o tiene cantidad 0, eliminarlo
            if (!$encontradoEnShopify || $currentQuantity == 0) {
                Log::info("Eliminando detalle de producto removido de Shopify", [
                    'detalle_id' => $detalle->id,
                    'producto_id' => $producto->id,
                    'producto_nombre' => $producto->nombre,
                    'cantidad_anterior' => $detalle->cantidad,
                    'encontrado_en_shopify' => $encontradoEnShopify,
                    'current_quantity' => $currentQuantity,
                    'venta_id' => $venta->id
                ]);

                // Ajustar inventario si no es cotización
                if ($venta->cotizacion != 1) {
                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();

                    if ($inventario) {
                        // Incrementar stock porque se está eliminando el producto
                        $inventario->increment('stock', $detalle->cantidad);

                        // Registrar en el kardex (con cantidad negativa para indicar devolución)
                        $inventario->kardex($venta, $detalle->cantidad, $detalle->precio, $producto->costo);

                        Log::info("Inventario ajustado por eliminación de producto", [
                            'producto_id' => $producto->id,
                            'cantidad_devuelta' => $detalle->cantidad,
                            'stock_actual' => $inventario->stock,
                            'venta_id' => $venta->id
                        ]);
                    }
                }

                // Eliminar el detalle
                $detalle->delete();
            }
        }

        // Procesar los line_items de Shopify
        foreach ($lineItems as $item) {
            // Validar que el item tenga los datos mínimos necesarios
            if (empty($item) || !is_array($item)) {
                Log::warning("Line item inválido o vacío", ['item' => $item]);
                continue;
            }

            // Verificar current_quantity - si es 0, el detalle ya debería haber sido eliminado arriba
            // Saltar este item para evitar recrearlo
            $currentQuantity = $item['current_quantity'] ?? $item['quantity'] ?? 0;
            if ($currentQuantity == 0) {
                Log::info("Saltando item con cantidad 0 - detalle ya eliminado", [
                    'variant_id' => $item['variant_id'] ?? 'N/A',
                    'title' => $item['title'] ?? 'N/A',
                    'venta_id' => $venta->id
                ]);
                continue;
            }

            // Buscar el producto por variant_id o SKU
            // Si hay múltiples productos con el mismo variant_id, usar el más reciente
            $producto = null;

            if (!empty($item['variant_id'])) {
                $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                    ->where('id_empresa', $venta->id_empresa)
                    ->orderBy('id', 'desc') // Usar el más reciente si hay duplicados
                    ->first();
            }

            if (!$producto && !empty($item['sku'])) {
                $producto = Producto::where('codigo', $item['sku'])
                    ->where('id_empresa', $venta->id_empresa)
                    ->orderBy('id', 'desc') // Usar el más reciente si hay duplicados
                    ->first();
            }

            // Si no se encuentra el producto, crearlo
            if (!$producto) {
                Log::info("Producto no encontrado, creando nuevo producto durante actualización", [
                    'variant_id' => $item['variant_id'] ?? 'N/A',
                    'sku' => $item['sku'] ?? 'N/A',
                    'title' => $item['title'] ?? 'N/A'
                ]);

                $productoData = $this->transformer->transformarProducto(
                    $item,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );
                $producto = Producto::create($productoData);

                Log::info("Producto creado durante actualización", ['producto_id' => $producto->id]);
            }

            // Buscar el detalle de venta existente por variant_id para evitar duplicados
            // Esto es importante porque puede haber múltiples productos con el mismo variant_id
            $variantId = $item['variant_id'] ?? null;
            $detalle = null;

            if ($variantId) {
                // Buscar detalle que tenga un producto con este variant_id
                $detalle = $venta->detalles()
                    ->whereHas('producto', function($query) use ($variantId) {
                        $query->where('shopify_variant_id', $variantId);
                    })
                    ->first();
            }

            // Si no se encontró por variant_id, buscar por id_producto como fallback
            if (!$detalle) {
                $detalle = $venta->detalles()
                    ->where('id_producto', $producto->id)
                    ->first();
            }

            // Si no existe el detalle, crearlo (producto nuevo agregado al pedido)
            if (!$detalle) {
                Log::info("Detalle de venta no encontrado - creando nuevo detalle para producto agregado", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'producto_nombre' => $producto->nombre,
                    'variant_id' => $variantId
                ]);

                // Crear el detalle usando el transformer
                $taxesIncluded = $request->taxes_included ?? false;
                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id, $usuario->id_empresa, $taxesIncluded);
                $detalleData['id_producto'] = $producto->id;
                $detalle = $venta->detalles()->create($detalleData);

                // Actualizar inventario para el nuevo producto
                if ($venta->cotizacion != 1) {
                    Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->decrement('stock', $item['quantity']);

                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();

                    if ($inventario) {
                        $inventario->kardex($venta, $item['quantity'], $item['price']);
                    }

                    Log::info("Inventario actualizado para producto nuevo agregado", [
                        'producto_id' => $producto->id,
                        'cantidad' => $item['quantity'],
                        'venta_id' => $venta->id
                    ]);
                }

                // Continuar al siguiente item ya que este es nuevo
                continue;
            } else {
                // Si se encontró un detalle pero con un producto diferente (mismo variant_id), actualizar el id_producto
                if ($detalle->id_producto != $producto->id) {
                    Log::info("Detalle encontrado con producto diferente - actualizando id_producto", [
                        'detalle_id' => $detalle->id,
                        'producto_anterior_id' => $detalle->id_producto,
                        'producto_nuevo_id' => $producto->id,
                        'variant_id' => $variantId,
                        'venta_id' => $venta->id
                    ]);
                    $detalle->update(['id_producto' => $producto->id]);
                }
            }

            $cantidadAnterior = $detalle->cantidad;
            // Usar current_quantity si está disponible, sino quantity
            $cantidadNueva = $item['current_quantity'] ?? $item['quantity'];
            $financialStatus = $request->financial_status ?? 'pending';
            $esReembolso = $financialStatus === 'refunded';

            Log::info("Comparando cantidades de producto", [
                'venta_id' => $venta->id,
                'producto_id' => $producto->id,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $cantidadNueva,
                'quantity_shopify' => $item['quantity'],
                'current_quantity_shopify' => $item['current_quantity'] ?? 'N/A',
                'fulfillable_quantity_shopify' => $item['fulfillable_quantity'] ?? 'N/A',
                'diferencia' => $cantidadNueva - $cantidadAnterior,
                'financial_status' => $financialStatus,
                'es_reembolso' => $esReembolso
            ]);

            // Solo actualizar si la cantidad ha cambiado O si es un reembolso
            if ($cantidadAnterior != $cantidadNueva || $esReembolso) {
                Log::info("Actualizando cantidad de producto", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'cantidad_anterior' => $cantidadAnterior,
                    'cantidad_nueva' => $cantidadNueva,
                    'diferencia' => $cantidadNueva - $cantidadAnterior,
                    'precio_original_detalle' => $detalle->precio,
                    'precio_shopify' => $item['price'] ?? 'N/A',
                    'es_reembolso' => $esReembolso,
                    'financial_status' => $financialStatus
                ]);

                // Para reembolsos, mantener la cantidad y total originales para evidencia
                if ($esReembolso) {
                    // Mantener cantidad y total originales para evidencia
                    $cantidadFinal = $cantidadAnterior; // Mantener cantidad original
                    $precioProducto = $detalle->precio; // Mantener precio original
                    $totalFinal = $detalle->total; // Mantener total original para evidencia
                    $ivaFinal = $detalle->iva; // Mantener IVA original
                    $gravadaFinal = $detalle->gravada; // Mantener gravada original

                    Log::info("Procesando reembolso - manteniendo valores originales", [
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad_original' => $cantidadAnterior,
                        'cantidad_mantenida' => $cantidadFinal,
                        'precio_mantenido' => $precioProducto,
                        'total_original' => $detalle->total,
                        'total_mantenido' => $totalFinal
                    ]);
                } else {
                    // Actualización normal
                    $cantidadFinal = $cantidadNueva;
                    $precioProducto = $detalle->precio;
                    if ($cantidadNueva == 0 && !empty($item['price'])) {
                        $precioProducto = floatval($item['price']);
                    }
                    $totalFinal = $cantidadFinal * $precioProducto;

                    // Recalcular IVA y gravada para el detalle individual
                    // $precioProducto ya es el precio sin IVA, así que calculamos el IVA correctamente
                    $ivaPorUnidad = round($precioProducto * 0.13, 2); // 13% IVA sobre precio sin IVA, redondeado a 2 decimales
                    $ivaFinal = round($cantidadFinal * $ivaPorUnidad, 2); // IVA total redondeado
                    $gravadaFinal = round($cantidadFinal * $precioProducto, 2); // Gravada = cantidad × precio sin IVA, redondeado
                }

                $detalle->update([
                    'cantidad' => $cantidadFinal,
                    'precio' => $precioProducto,
                    'total' => $totalFinal,
                    'iva' => $ivaFinal,
                    'gravada' => $gravadaFinal
                ]);

                // Ajustar el inventario solo si NO es un reembolso
                if (!$esReembolso) {
                    $diferenciaStock = $cantidadNueva - $cantidadAnterior;

                    if ($diferenciaStock != 0) {
                        $inventario = Inventario::where('id_producto', $producto->id)
                            ->where('id_bodega', $venta->id_bodega)
                            ->first();

                        if ($inventario) {
                            if ($diferenciaStock > 0) {
                                // Se agregaron productos, reducir stock
                                $inventario->decrement('stock', $diferenciaStock);
                            } else {
                                // Se quitaron productos, incrementar stock
                                $inventario->increment('stock', abs($diferenciaStock));
                            }

                            // Registrar en el kardex
                            $inventario->kardex($venta, abs($diferenciaStock), $detalle->precio, $producto->costo);

                            Log::info("Inventario ajustado por cambio de cantidad", [
                                'producto_id' => $producto->id,
                                'diferencia_stock' => $diferenciaStock,
                                'stock_actual' => $inventario->stock
                            ]);
                        }
                    }
                } else {
                    Log::info("Reembolso detectado - no se ajusta inventario", [
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad_mantenida' => $cantidadAnterior
                    ]);
                }
            } else {
                Log::info("Cantidad sin cambios para producto", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidadAnterior
                ]);
            }
        }

        // Recalcular totales de la venta
        $this->recalcularTotalesVenta($venta);
    }

    /**
     * Actualiza los envíos de una venta cuando cambian en Shopify
     *
     * @param Venta $venta
     * @param Request $request
     * @param User $usuario
     * @return void
     */
    public function actualizarEnvio(Venta $venta, Request $request, User $usuario): void
    {
        Log::info("Iniciando actualización de envíos", [
            'venta_id' => $venta->id,
            'shopify_order_id' => $request->id,
            'shipping_lines_count' => count($request->shipping_lines ?? [])
        ]);

        // Obtener shipping_lines del request
        $shippingLines = $request->shipping_lines ?? [];

        if (empty($shippingLines)) {
            Log::info("No hay shipping_lines para actualizar", [
                'venta_id' => $venta->id
            ]);
            return;
        }

        // Obtener todos los detalles de envío existentes (productos tipo Servicio en categoría envios)
        $detallesEnvioExistentes = $venta->detalles()
            ->whereHas('producto', function($query) use ($venta) {
                $query->where('tipo', 'Servicio')
                    ->whereHas('categoria', function($q) {
                        $q->where('nombre', 'envios');
                    });
            })
            ->get();

        // Crear un mapa de envíos de Shopify por título
        $enviosShopify = [];
        foreach ($shippingLines as $shippingLine) {
            $title = $shippingLine['title'] ?? '';
            $isRemoved = $shippingLine['is_removed'] ?? false;

            if (!empty($title) && !$isRemoved) {
                $enviosShopify[$title] = $shippingLine;
            }
        }

        // Eliminar envíos que ya no están en Shopify (is_removed: true o no están en la lista)
        foreach ($detallesEnvioExistentes as $detalleEnvio) {
            $tituloEnvio = $detalleEnvio->descripcion;

            // Verificar si el envío fue removido o ya no existe en Shopify
            $fueRemovido = false;
            foreach ($shippingLines as $shippingLine) {
                if (($shippingLine['title'] ?? '') === $tituloEnvio && ($shippingLine['is_removed'] ?? false)) {
                    $fueRemovido = true;
                    break;
                }
            }

            if ($fueRemovido || !isset($enviosShopify[$tituloEnvio])) {
                Log::info("Eliminando detalle de envío removido", [
                    'detalle_id' => $detalleEnvio->id,
                    'titulo_envio' => $tituloEnvio,
                    'venta_id' => $venta->id
                ]);
                $detalleEnvio->delete();
            }
        }

        // Procesar envíos nuevos o actualizados
        $enviosProcesados = [];
        foreach ($shippingLines as $shippingLine) {
            $title = $shippingLine['title'] ?? '';
            $isRemoved = $shippingLine['is_removed'] ?? false;

            if (empty($title) || $isRemoved) {
                continue;
            }

            // Buscar si ya existe un detalle con este título
            $detalleExistente = $venta->detalles()
                ->where('descripcion', $title)
                ->whereHas('producto', function($query) {
                    $query->where('tipo', 'Servicio')
                        ->whereHas('categoria', function($q) {
                            $q->where('nombre', 'envios');
                        });
                })
                ->first();

            if ($detalleExistente) {
                // Actualizar el detalle existente si el precio cambió
                $precioNuevo = floatval($shippingLine['discounted_price'] ?? $shippingLine['price'] ?? 0);
                $precioSinIVA = $this->impuestosService->calcularPrecioSinImpuesto($precioNuevo, $venta->id_empresa);
                $ivaNuevo = $precioNuevo - $precioSinIVA;

                if (abs($detalleExistente->precio_sin_iva - $precioSinIVA) > 0.01) {
                    Log::info("Actualizando precio de envío existente", [
                        'detalle_id' => $detalleExistente->id,
                        'titulo_envio' => $title,
                        'precio_anterior' => $detalleExistente->precio_sin_iva,
                        'precio_nuevo' => $precioSinIVA,
                        'venta_id' => $venta->id
                    ]);

                    $detalleExistente->update([
                        'precio_sin_iva' => $precioSinIVA,
                        'precio_con_iva' => $precioNuevo,
                        'total' => $precioSinIVA,
                        'gravada' => $precioSinIVA,
                        'iva' => $ivaNuevo
                    ]);
                }

                $enviosProcesados[] = $detalleExistente->id;
            } else {
                // Crear nuevo detalle de envío
                $detallesEnvio = $this->shippingService->procesarTiposEnvio(
                    [$shippingLine],
                    $venta->id,
                    $venta->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );

                if (!empty($detallesEnvio)) {
                    $enviosProcesados[] = $detallesEnvio[0]->id;
                    Log::info("Nuevo detalle de envío creado durante actualización", [
                        'detalle_id' => $detallesEnvio[0]->id,
                        'titulo_envio' => $title,
                        'venta_id' => $venta->id
                    ]);
                }
            }
        }

        Log::info("Actualización de envíos completada", [
            'venta_id' => $venta->id,
            'envios_procesados' => count($enviosProcesados),
            'envios_eliminados' => count($detallesEnvioExistentes) - count($enviosProcesados)
        ]);

        // Recalcular totales después de actualizar envíos
        $this->recalcularTotalesVenta($venta);
    }
}

