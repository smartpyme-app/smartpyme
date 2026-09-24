<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Helpers\ShopifyHelper;
use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToShopify;
use App\Jobs\ConsolidarProductosShopifyJob;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Services\ShopifyApiClient;
use Illuminate\Http\Request;
use App\Services\ShopifyTransformer;
use App\Models\Admin\ShopifyLocation;
use App\Services\ShopifyImageService;
use App\Services\ShopifyLocationService;
use App\Services\ShopifyTokenService;
use App\Services\ShippingService;
use App\Services\Shopify\ShopifyVentaService;
use App\Services\Shopify\ShopifyClienteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Inventario\Kardex;
use App\Services\ShopifySyncCache;
use App\Services\FidelizacionCliente\ConsumoPuntosService;

class ShopifyController extends Controller
{
    protected $transformer;
    protected $cache;
    protected $shippingService;
    protected $impuestosService;
    protected $shopifyVentaService;
    protected $shopifyClienteService;
    protected $imageService;


    public function __construct(
        ShopifyTransformer $transformer,
        ShopifySyncCache $cache,
        ShippingService $shippingService,
        \App\Services\ImpuestosService $impuestosService,
        ShopifyVentaService $shopifyVentaService,
        ShopifyClienteService $shopifyClienteService,
        ShopifyImageService $imageService
    ) {
        $this->transformer = $transformer;
        $this->cache = $cache;
        $this->shippingService = $shippingService;
        $this->impuestosService = $impuestosService;
        $this->shopifyVentaService = $shopifyVentaService;
        $this->shopifyClienteService = $shopifyClienteService;
        $this->imageService = $imageService;
    }

    public function handle($tokenEmpresa, Request $request)
    {
        Log::info("Webhook Shopify recibido para token: {$tokenEmpresa}");
        Log::info("Datos del webhook: ", $request->all());

        $webhookTopic = $request->header('X-Shopify-Topic');
        $webhookId = $request->header('X-Shopify-Webhook-Id');

        ShopifyHelper::log("=== WEBHOOK SHOPIFY RECIBIDO ===", [
            'topic' => $webhookTopic,
            'webhook_id' => $webhookId,
            'token' => substr($tokenEmpresa, 0, 8) . '...',
            'ip' => $request->ip(),
        ]);

        if ($webhookId && !in_array($webhookTopic, ['orders/create', 'orders/updated', 'orders/cancelled'])) {
            try {
                $cacheKey = "shopify_webhook_processed_{$webhookId}";
                if (!\Illuminate\Support\Facades\Cache::add($cacheKey, true, 3600)) {
                    ShopifyHelper::log("Webhook ya procesado o en ejecución (deduplicado)", [
                        'webhook_id' => $webhookId,
                        'topic' => $webhookTopic,
                    ]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Webhook ya procesado previamente'
                    ], 200);
                }
            } catch (\Throwable $e) {
                // Si falla cache, continuar
            }
        }

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            ShopifyHelper::log("Token de empresa Shopify no válido: {$tokenEmpresa}", [], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            ShopifyHelper::log("Usuario no encontrado para empresa", [
                'empresa_id' => $empresa->id,
                'empresa_nombre' => $empresa->nombre,
                'token' => substr($tokenEmpresa, 0, 8) . '...'
            ], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        Log::info("Usuario encontrado para webhook", [
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->name,
            'id_empresa' => $usuario->id_empresa,
            'id_bodega' => $usuario->id_bodega,
            'id_sucursal' => $usuario->id_sucursal,
            'shopify_status' => $usuario->shopify_status
        ]);

        // Verificar que el usuario tenga bodega asignada
        if (!$usuario->id_bodega) {
            ShopifyHelper::log("Usuario sin bodega asignada", [
                'usuario_id' => $usuario->id,
                'usuario_nombre' => $usuario->name,
                'id_empresa' => $usuario->id_empresa
            ], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario sin bodega asignada'
            ], 400);
        }

        try {
            switch ($webhookTopic) {
                case 'test':
                    ShopifyHelper::log("Procesando prueba webhook");
                    return $this->procesarPruebaWebhook($request, $empresa);

                case 'orders/create':
                    ShopifyHelper::log(">> ENRUTANDO A orders/create", [
                        'shopify_order_id' => $request->id,
                        'order_number' => $request->order_number,
                        'financial_status' => $request->financial_status,
                        'total_line_items' => count($request->line_items ?? []),
                    ]);
                    return $this->procesarVenta($tokenEmpresa, $request);

                case 'orders/cancelled':
                    ShopifyHelper::log(">> ENRUTANDO A orders/cancelled", [
                        'shopify_order_id' => $request->id,
                        'financial_status' => $request->financial_status,
                        'cancel_reason' => $request->cancel_reason,
                    ]);
                    return $this->procesarVentaCancelada($tokenEmpresa, $request);

                case 'orders/updated':
                    ShopifyHelper::log(">> ENRUTANDO A orders/updated", [
                        'shopify_order_id' => $request->id,
                        'financial_status' => $request->financial_status,
                    ]);
                    // Shopify reintenta el mismo webhook_id si la respuesta tarda.
                    // Procesarlo otra vez duplica el envío. Si esta pasada falla, se suelta
                    // la llave para que el reintento sí pueda corregir la venta.
                    $ordersUpdatedKey = $webhookId ? "shopify_webhook_processed_{$webhookId}" : null;
                    if ($ordersUpdatedKey && !\Illuminate\Support\Facades\Cache::add($ordersUpdatedKey, true, 3600)) {
                        ShopifyHelper::log("Webhook ya procesado o en ejecución (deduplicado)", [
                            'webhook_id' => $webhookId,
                            'topic' => $webhookTopic,
                        ]);
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Webhook ya procesado previamente'
                        ], 200);
                    }
                    $respuestaVenta = $this->procesarVentaActualizada($tokenEmpresa, $request);
                    if ($ordersUpdatedKey && $respuestaVenta->getStatusCode() !== 200) {
                        \Illuminate\Support\Facades\Cache::forget($ordersUpdatedKey);
                    }
                    return $respuestaVenta;

                case 'orders/edited':
                    ShopifyHelper::log("Webhook recibido: orders/edited");
                    return response()->json([
                        'status' => 'success',
                        'mensaje' => 'orders/edited recibido - usar orders/updated para información completa'
                    ], 200);

                case 'customers/create':
                    ShopifyHelper::log("Webhook recibido: customers/create", ['customer_id' => $request->id]);
                    return $this->procesarClienteCreado($request, $empresa, $usuario);

                case 'customers/update':
                    ShopifyHelper::log("Webhook recibido: customers/update", ['customer_id' => $request->id]);
                    return $this->procesarClienteActualizado($request, $empresa, $usuario);

                case 'products/create':
                    ShopifyHelper::log("Webhook recibido: products/create", ['shopify_product_id' => $request->id]);
                    return $this->procesarProductoActualizado($request, $empresa, $usuario);

                case 'products/update':
                    ShopifyHelper::log("Webhook recibido: products/update", ['shopify_product_id' => $request->id]);
                    return $this->procesarProductoActualizado($request, $empresa, $usuario);

                case 'products/delete':
                    ShopifyHelper::log("Webhook recibido: products/delete", ['shopify_product_id' => $request->id]);
                    return $this->procesarProductoEliminadoShopify($request, $empresa);

                case 'draft_orders/create':
                    ShopifyHelper::log("Webhook recibido: draft_orders/create (ignorado)", ['draft_order_id' => $request->id]);
                    return response()->json([
                        'status' => 'ignored',
                        'mensaje' => 'Draft orders no se procesan - solo órdenes pagadas'
                    ], 200);

                case 'inventory_levels/update':
                    ShopifyHelper::log(">> ENRUTANDO A inventory_levels/update", [
                        'inventory_item_id' => $request->input('inventory_item_id'),
                        'location_id' => $request->input('location_id'),
                        'available' => $request->input('available'),
                        'updated_at' => $request->input('updated_at'),
                    ]);
                    return $this->procesarInventarioActualizadoShopify($request, $empresa, $usuario);

                case 'inventory_transfers/complete':
                case 'inventory_transfers/updated':
                case 'transfers/complete':
                case 'transfers/update':
                    ShopifyHelper::log(">> ENRUTANDO A traslado de inventario ({$webhookTopic})", [
                        'topic' => $webhookTopic,
                        'transfer_id' => $request->input('id'),
                        'origin' => $request->input('origin'),
                        'destination' => $request->input('destination'),
                        'line_items_count' => count($request->input('line_items', [])),
                    ]);
                    return $this->procesarTrasladoInventarioShopify($request, $empresa, $usuario, $webhookTopic);

                case 'locations/create':
                    ShopifyHelper::log(">> ENRUTANDO A locations/create", [
                        'shopify_location_id' => $request->id,
                        'name' => $request->name,
                    ]);
                    return $this->procesarUbicacionCreadaShopify($request, $empresa);

                case 'locations/update':
                case 'locations/activate':
                case 'locations/deactivate':
                    ShopifyHelper::log(">> ENRUTANDO A {$webhookTopic}", [
                        'shopify_location_id' => $request->id,
                        'name' => $request->name,
                        'topic' => $webhookTopic,
                    ]);
                    return $this->procesarUbicacionActualizadaShopify($request, $empresa, $webhookTopic);

                case 'locations/delete':
                    ShopifyHelper::log(">> ENRUTANDO A locations/delete", [
                        'shopify_location_id' => $request->id,
                    ]);
                    return $this->procesarUbicacionEliminadaShopify($request, $empresa);

                default:
                    ShopifyHelper::log("Tipo de webhook no manejado: {$webhookTopic}", [], 'warning');
                    return response()->json(['message' => 'Webhook recibido pero no procesado'], 200);
            }
        } catch (\Exception $e) {
            ShopifyHelper::log("Error procesando webhook Shopify: " . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function procesarProductoActualizado(Request $request, $empresa, $usuario)
    {
        // ShopifyHelper::log("procesarProductoActualizado iniciado", [
            // 'shopify_product_id' => $request->id,
            // 'title' => $request->title,
            // 'variants_count' => count($request->variants ?? [])
        // ]);

        $productosData = $this->transformer->transformarProductoDesdeShopify(
            $request->all(),
            $empresa->id,
            $usuario->id,
            $usuario->id_sucursal,
            true, // incluirDrafts
            false // NO es importación masiva (es webhook)
        );

        // Verificar si se obtuvieron productos válidos
        if (empty($productosData)) {
            Log::warning("No se pudieron transformar productos válidos desde Shopify", [
                'shopify_product_id' => $request->id
            ]);
            return response()->json(['status' => 'success', 'message' => 'No valid products to process'], 200);
        }

        $categoriaData = $this->transformer->transformarCategoriaDesdeShopify(
            $request->all(),
            $empresa->id
        );

        // Producto simple de una compra que en Shopify pasó a tener variantes reales.
        // Se deja en 0, desvinculado, solo como rastro de la compra.
        $this->retirarProductoSimpleAlDividir($request->id, $productosData, $empresa->id, $usuario);

        foreach ($productosData as $productoData) {
            $variantImageId = $productoData['shopify_variant_image_id'] ?? null;
            unset($productoData['shopify_variant_image_id']);

            $producto = $this->buscarProductoExistente($request->id, $productoData, $empresa->id);
            $categoria = $this->obtenerCategoria($request->all(), $categoriaData, $empresa->id);
            $productoData['id_categoria'] = $categoria->id;

            if ($producto) {
                // España Dev: Si la sincronización local está activa para este producto, omitir webhook entrante para prevenir bucles y reseteos a 0
                if ($this->cache->isLocked($producto->id) || Cache::has("shopify_syncing_inv_{$producto->id}")) {
                    Log::channel('shopify')->info("ShopifyController: Omitiendo webhook para producto #{$producto->id} porque la sincronización local está activa (lockSync)");
                    $this->procesarImagenes($request, $producto->id, $variantImageId);
                    continue;
                }

                $isDifferent = $this->cache->isShopifyDataDifferent($producto, $productoData);
                
                // España Dev: detectar si el stock total o por sucursal difiere para sincronizarlo
                $stockTotalShopify = isset($productoData['_stock']) ? (int) $productoData['_stock'] : null;
                $stockTotalLocal = (int) Inventario::where('id_producto', $producto->id)->sum('stock');
                $stockDifiere = ($stockTotalShopify !== null && $stockTotalShopify !== $stockTotalLocal);

                if (!$stockDifiere) {
                    $ubicacionesMapeadasCount = ShopifyLocation::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->where('sincronizar_stock', true)
                        ->whereNotNull('id_bodega')
                        ->count();
                    if ($ubicacionesMapeadasCount > 1) {
                        $bodegasConInv = Inventario::where('id_producto', $producto->id)->count();
                        if ($bodegasConInv < $ubicacionesMapeadasCount) {
                            $stockDifiere = true;
                        }
                    }
                }

                if ($isDifferent || $stockDifiere) {
                    $this->cache->lockSync($producto->id);

                    $this->actualizarProductoExistente($producto, $productoData, $usuario);

                    $producto->fresh();
                    $this->cache->saveProductSnapshot($producto);

                    // Log::info("Producto actualizado desde Shopify", ['producto_id' => $producto->id]);
                } else {
                    Log::info("Producto sin cambios desde Shopify", ['producto_id' => $producto->id]);
                }

                // Sincronizar imágenes SIEMPRE (independiente de si cambiaron los datos del producto),
                // validando URL y reemplazando solo si la URL o el hash cambiaron.
                $this->procesarImagenes($request, $producto->id, $variantImageId);
            } else {
                // El SKU repetido solo bloquea si pertenece a otro producto.
                // Las variantes del mismo producto de Shopify pueden compartir el SKU de la compra.
                $duplicadoPorSKU = !empty($productoData['codigo']) &&
                    Producto::where('codigo', $productoData['codigo'])
                        ->where('id_empresa', $empresa->id)
                        ->where('enable', '!=', '0')
                        ->where(function ($q) use ($request) {
                            $q->whereNull('shopify_product_id')
                              ->orWhere('shopify_product_id', '!=', $request->id);
                        })
                        ->exists();

                if ($duplicadoPorSKU) {
                    Log::warning("Intento de crear producto duplicado por SKU", [
                        'shopify_product_id' => $request->id,
                        'sku' => $productoData['codigo']
                    ]);
                    continue; // Saltar este producto
                }

                $nuevoProducto = $this->crearNuevoProducto($productoData, $usuario, $request, $variantImageId);

                if ($nuevoProducto) {
                    $this->cache->lockSync($nuevoProducto->id);
                    $this->cache->saveProductSnapshot($nuevoProducto);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Procesa la eliminación de un producto recibida vía webhook products/delete de Shopify.
     * Desactiva lógicamente el producto y todas sus variantes manteniendo IDs para trazabilidad histórica.
     */
    private function procesarProductoEliminadoShopify(Request $request, $empresa)
    {
        $shopifyProductId = $request->input('id') ?? $request->id;

        if (empty($shopifyProductId)) {
            ShopifyHelper::log("products/delete: Webhook recibido sin ID de producto", [], 'warning');
            return response()->json([
                'status' => 'ignored',
                'mensaje' => 'ID de producto no provisto'
            ], 200);
        }

        $productos = Producto::withoutGlobalScopes()
            ->where('id_empresa', $empresa->id)
            ->where('shopify_product_id', $shopifyProductId)
            ->get();

        if ($productos->isEmpty()) {
            ShopifyHelper::log("products/delete: Producto Shopify no encontrado localmente", [
                'shopify_product_id' => $shopifyProductId,
                'empresa_id' => $empresa->id,
            ]);
            return response()->json([
                'status' => 'success',
                'mensaje' => 'Producto no encontrado localmente o ya procesado',
                'productos_afectados' => 0
            ], 200);
        }

        $afectados = 0;
        foreach ($productos as $producto) {
            $producto->enable = '0';
            if (method_exists($producto, 'saveQuietly')) {
                $producto->saveQuietly();
            } else {
                Producto::withoutEvents(function () use ($producto) {
                    $producto->save();
                });
            }

            Cache::forget("shopify_product_{$producto->id}");
            $afectados++;
        }

        ShopifyHelper::log("products/delete: Producto(s) desactivado(s) exitosamente", [
            'shopify_product_id' => $shopifyProductId,
            'empresa_id' => $empresa->id,
            'productos_afectados' => $afectados,
        ]);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Producto(s) desactivado(s) exitosamente',
            'productos_afectados' => $afectados
        ], 200);
    }

    private function buscarProductoExistente($shopifyId, $productoData, $empresaId)
    {
        // Búsqueda principal por IDs de Shopify
        $producto = Producto::where('shopify_product_id', $shopifyId)
            ->where('shopify_variant_id', $productoData['shopify_variant_id'])
            ->where('id_empresa', $empresaId)
            ->where('enable', '!=', '0')
            ->first();

        // SKU solo reengancha un producto que aún no tiene variante, o la misma variante.
        // Una variante nueva con el SKU de la compra no se pega al producto original.
        if (!$producto && !empty($productoData['shopify_sku'])) {
            $candidato = Producto::where('shopify_sku', $productoData['shopify_sku'])
                ->where('id_empresa', $empresaId)
                ->where('enable', '!=', '0')
                ->first();
            if ($candidato && (
                empty($candidato->shopify_variant_id)
                || (string) $candidato->shopify_variant_id === (string) $productoData['shopify_variant_id']
            )) {
                $producto = $candidato;
            }
        }

        // Si no se encuentra, buscar por SKU del proveedor como respaldo (para productos creados antes de la integración)
        if (!$producto && !empty($productoData['codigo'])) {
            $producto = Producto::where('codigo', $productoData['codigo'])
                ->where('id_empresa', $empresaId)
                ->where('enable', '!=', '0')
                ->whereNull('shopify_product_id') // Solo productos sin ID de Shopify
                ->first();
                
            // Si encontramos uno por SKU, actualizamos sus IDs de Shopify
            if ($producto) {
                $producto->update([
                    'shopify_product_id' => $shopifyId,
                    'shopify_variant_id' => $productoData['shopify_variant_id'],
                    'shopify_inventory_item_id' => $productoData['shopify_inventory_item_id'] ?? null,
                ]);
            }
        }

        return $producto;
    }

    private function obtenerCategoria($requestData, $categoriaData, $empresaId)
    {
        // Si no hay categoría en Shopify o los datos de categoría están vacíos
        if (empty($requestData['category']) || empty($categoriaData['nombre'])) {
            return $this->buscarCategoria('General', $empresaId);
        }
        
        return $this->buscarCategoria($categoriaData['nombre'], $empresaId);
    }

    /**
     * Procesa el webhook inventory_levels/update de Shopify (ajuste de cantidad desde Shopify).
     * Actualiza el inventario en SmartPyme y registra en kardex "Actualización de producto desde Shopify" con entrada/salida.
     */
    private function procesarInventarioActualizadoShopify(Request $request, $empresa, $usuario)
    {
        $inventoryItemId = $request->input('inventory_item_id');
        $locationId = $request->input('location_id');
        $available = (int) $request->input('available', 0);

        ShopifyHelper::log("inventory_levels/update: Procesando ajuste de inventario", [
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available_shopify' => $available,
            'updated_at' => $request->input('updated_at'),
        ]);

        if (empty($inventoryItemId)) {
            ShopifyHelper::log("inventory_levels/update: [IGNORADO] Sin inventory_item_id", [], 'warning');
            return response()->json(['status' => 'ignored', 'message' => 'Missing inventory_item_id'], 200);
        }

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('shopify_inventory_item_id', $inventoryItemId)
            ->first();

        if (!$producto) {
            $this->guardarInventarioPendiente($empresa->id, $inventoryItemId, $locationId, $available);
            ShopifyHelper::log("inventory_levels/update: [PENDIENTE] Producto aún no vinculado, se aplicará al crear la variante", [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available_shopify' => $available,
            ], 'warning');
            return response()->json(['status' => 'ignored', 'message' => 'Product not linked'], 200);
        }

        // España Dev: Si la sincronización saliente está activa para este producto, omitir webhook entrante
        if ($this->cache->isLocked($producto->id) || Cache::has("shopify_syncing_inv_{$producto->id}")) {
            ShopifyHelper::log("inventory_levels/update: [OMITIDO] Sincronización saliente activa para producto #{$producto->id}", [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
            ]);
            return response()->json(['status' => 'ignored', 'message' => 'Outbound sync in progress'], 200);
        }

        // Resolver la bodega destino según el location_id que envía Shopify
        $bodegaId = null;
        if (!empty($locationId)) {
            $mapping = \App\Models\Admin\ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $locationId)
                ->first();

            if ($mapping && !empty($mapping->id_bodega)) {
                $bodegaId = $mapping->id_bodega;
            }
        }

        // Fallback a la bodega del usuario conectado si no hay mapeo específico
        if (!$bodegaId) {
            $bodegaId = $usuario->id_bodega;
        }

        $inventario = Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $bodegaId)
            ->first();

        $stockAnterior = $inventario ? (int) $inventario->stock : 0;

        ShopifyHelper::log("inventory_levels/update: Datos resueltos", [
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'bodega_id' => $bodegaId,
            'stock_smartpyme' => $stockAnterior,
            'available_shopify' => $available,
        ]);

        // 1. Si el stock en SmartPyme ya es igual al stock available de Shopify, no hay nada que cambiar
        if ($stockAnterior === $available) {
            ShopifyHelper::log("inventory_levels/update: [OMITIDO] Stock en SmartPyme ({$stockAnterior}) ya es igual al available de Shopify ({$available})", [
                'producto_id' => $producto->id,
                'bodega_id' => $bodegaId,
                'stock' => $stockAnterior,
            ]);
            return response()->json(['status' => 'success', 'message' => 'Stock ya sincronizado'], 200);
        }

        // 2. En SmartPyme (ERP), las reducciones de inventario son gestionadas exclusivamente
        // por el flujo de ventas/órdenes (orders/create, convertirCotizacionAVenta), las cuales
        // emiten el documento fiscal (Factura/CCF) y registran la salida formal en Kardex.
        // Toda orden en Shopify emite concurrentemente inventory_levels/update con available < stock;
        // procesar reducciones aquí produce condiciones de carrera y doble descuento en Kardex.
        //
        // BUG-2 mitigation: si la reducción llega pero no hay venta reciente en SmartPyme que la
        // justifique, es probable que el webhook orders/create se haya perdido. Se registra una
        // alerta de discrepancia para que pueda reconciliarse sin aplicar el descuento de forma
        // insegura (lo que causaría doble descuento cuando orders/create SÍ llegue).
        if ($available < $stockAnterior) {
            $delta = $stockAnterior - $available; // unidades reducidas en Shopify

            // Verificar si ya existe una venta reciente en SmartPyme que justifique esta reducción.
            // Ventana: 5 minutos — suficiente para que orders/create haya sido procesado.
            $ventaReciente = \App\Models\Ventas\DetalleVenta::whereHas('venta', function ($q) use ($empresa) {
                    $q->where('id_empresa', $empresa->id)
                      ->where('created_at', '>=', now()->subMinutes(5));
                })
                ->whereHas('producto', function ($q) use ($producto) {
                    $q->where('id', $producto->id);
                })
                ->exists();

            if ($ventaReciente) {
                // orders/create ya fue procesado — reducción esperada, ignorar correctamente.
                ShopifyHelper::log("inventory_levels/update: [OMITIDO] Reducción cubierta por venta reciente en SmartPyme", [
                    'producto_id'      => $producto->id,
                    'bodega_id'        => $bodegaId,
                    'stock_smartpyme'  => $stockAnterior,
                    'available_shopify'=> $available,
                    'delta'            => -$delta,
                ]);
            } else {
                // No hay venta reciente: posible webhook orders/create perdido.
                // NO aplicamos la reducción para evitar doble descuento cuando orders/create llegue tarde.
                // Se registra como warning para reconciliación manual o automatizada.
                Log::channel('shopify')->warning("inventory_levels/update: [DISCREPANCIA STOCK] Reducción sin venta reciente en SmartPyme — posible webhook orders/create perdido", [
                    'producto_id'          => $producto->id,
                    'producto_nombre'      => $producto->nombre,
                    'bodega_id'            => $bodegaId,
                    'stock_smartpyme'      => $stockAnterior,
                    'available_shopify'    => $available,
                    'unidades_sin_cubrir'  => $delta,
                    'inventory_item_id'    => $inventoryItemId,
                    'location_id'          => $locationId,
                    'accion_recomendada'   => "Verificar si existe orden en Shopify para este producto y reconciliar stock manualmente si orders/create no llega.",
                ]);

                // Guardar la discrepancia en cache (TTL 30min) para que un job de reconciliación
                // pueda detectarla y actuar. Clave: shopify_stock_discrepancy_{producto_id}_{bodega_id}
                Cache::put("shopify_stock_discrepancy_{$producto->id}_{$bodegaId}", [
                    'producto_id'      => $producto->id,
                    'bodega_id'        => $bodegaId,
                    'stock_smartpyme'  => $stockAnterior,
                    'available_shopify'=> $available,
                    'detected_at'      => now()->toISOString(),
                    'inventory_item_id'=> $inventoryItemId,
                ], 1800);
            }

            return response()->json([
                'status'  => 'ignored',
                'message' => 'Reducciones de stock son gestionadas exclusivamente por el flujo de ventas/órdenes.',
            ], 200);
        }

        ShopifyHelper::log("inventory_levels/update: [APLICANDO AJUSTE DIRECTO] Modificando inventario por cambio en Shopify", [
            'producto_id' => $producto->id,
            'bodega_id' => $bodegaId,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $available,
            'delta' => $available - $stockAnterior,
        ]);

        $this->cache->lockSync($producto->id);

        $this->actualizarInventario(
            $producto->id,
            $available,
            $bodegaId,
            $usuario->id,
            ['origen' => 'shopify', 'tipo' => 'ajuste_inventario', 'stock_anterior' => $stockAnterior, 'location_id' => $locationId]
        );

        if ($inventario) {
            $this->cache->saveInventorySnapshot($inventario->fresh(), $producto->id);
        }

        ShopifyHelper::log("inventory_levels/update: [COMPLETADO] Inventario actualizado exitosamente", [
            'producto_id' => $producto->id,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $available,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Inventario actualizado'], 200);
    }

    /**
     * Procesa los webhooks de traslados de inventario de Shopify (inventory_transfers/complete, inventory_transfers/updated).
     * Crea el registro formal en la tabla traslados y asienta las salidas/entradas correspondientes en kardex.
     */
    private function procesarTrasladoInventarioShopify(Request $request, $empresa, $usuario, $webhookTopic = 'inventory_transfers/complete')
    {
        $rawTransferId = $request->input('id');
        $transferId = preg_replace('/[^0-9]/', '', (string)$rawTransferId);

        if (empty($transferId)) {
            ShopifyHelper::log("inventory_transfers: [IGNORADO] Sin id de traslado válido", [], 'warning');
            return response()->json(['status' => 'ignored', 'message' => 'Missing or invalid transfer id'], 200);
        }

        // 2. Extraer datos del payload
        $originRaw = $request->input('origin.id') ?? $request->input('origin_location_id');
        $destRaw = $request->input('destination.id') ?? $request->input('destination_location_id');
        $lineItems = $request->input('line_items', []);
        $transferName = "#{$transferId}";

        // Si faltan line_items o ubicaciones en el payload (habitual en webhooks de transferencias de Shopify),
        // consultar la API GraphQL para obtener el detalle completo del traslado.
        if ((empty($lineItems) || empty($originRaw) || empty($destRaw)) && !empty($empresa->shopify_store_url)) {
            $transferData = $this->obtenerDetallesTrasladoShopify($empresa, $transferId);
            if ($transferData) {
                $status = strtoupper($transferData['status'] ?? '');
                // Para eventos de actualización, solo procesar cuando la transferencia esté efectivamente realizada/completada
                if (!in_array($status, ['TRANSFERRED', 'COMPLETED'])) {
                    ShopifyHelper::log("inventory_transfers: [OMITIDO] Traslado {$transferId} en estado '{$status}', solo se procesa al completarse/transferirse", [
                        'status' => $status,
                        'transfer_id' => $transferId,
                    ]);
                    return response()->json(['status' => 'ignored', 'message' => "Transfer in status {$status}"], 200);
                }

                $transferName = $transferData['name'] ?? $transferName;
                if (empty($originRaw)) {
                    $originRaw = $transferData['origin']['location']['id'] ?? null;
                }
                if (empty($destRaw)) {
                    $destRaw = $transferData['destination']['location']['id'] ?? null;
                }

                if (empty($lineItems) && !empty($transferData['lineItems']['edges'])) {
                    foreach ($transferData['lineItems']['edges'] as $edge) {
                        $node = $edge['node'] ?? [];
                        $qty = (float) ($node['totalQuantity'] ?? $node['shippedQuantity'] ?? 0);
                        $invItemId = !empty($node['inventoryItem']['id']) ? preg_replace('/[^0-9]/', '', (string)$node['inventoryItem']['id']) : null;
                        if ($qty > 0 && $invItemId) {
                            $lineItems[] = [
                                'inventory_item_id' => $invItemId,
                                'quantity' => $qty,
                                'title' => $node['title'] ?? '',
                            ];
                        }
                    }
                }
            }
        }

        $originLocationId = !empty($originRaw) ? preg_replace('/[^0-9]/', '', (string)$originRaw) : null;
        $destLocationId = !empty($destRaw) ? preg_replace('/[^0-9]/', '', (string)$destRaw) : null;

        if (empty($originLocationId) && empty($destLocationId)) {
            ShopifyHelper::log("inventory_transfers: [IGNORADO] Faltan ubicaciones de origen o destino en el payload", [
                'origin' => $originRaw,
                'destination' => $destRaw,
                'transfer_id' => $transferId,
            ], 'warning');
            return response()->json(['status' => 'ignored', 'message' => 'Missing origin or destination location'], 200);
        }

        // 3. Idempotencia: Verificar si ya existe este traslado o ajuste registrado en SmartPyme
        $conceptoReferencia = "SHOPIFY-TRANSFER-{$transferId}";
        if (!empty($empresa->id)) {
            $yaExisteTraslado = \App\Models\Inventario\Traslado::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('concepto', 'like', "%{$conceptoReferencia}%")
                ->exists();

            $yaExisteKardex = Kardex::where('referencia', $conceptoReferencia)
                ->orWhere('detalle', 'like', "%(Transferencia {$transferName})%")
                ->exists();

            if ($yaExisteTraslado || $yaExisteKardex) {
                ShopifyHelper::log("inventory_transfers: [OMITIDO] Traslado {$transferId} ya procesado anteriormente en SmartPyme", [
                    'transfer_id' => $transferId,
                    'empresa_id' => $empresa->id,
                ]);
                return response()->json(['status' => 'ignored', 'message' => 'Traslado ya procesado previamente'], 200);
            }
        }

        // 4. Mapear ubicaciones de Shopify a bodegas en SmartPyme
        $originMapping = !empty($originLocationId)
            ? \App\Models\Admin\ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $originLocationId)
                ->first()
            : null;

        $destMapping = !empty($destLocationId)
            ? \App\Models\Admin\ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('shopify_location_id', $destLocationId)
                ->first()
            : null;

        $bodegaOrigenId = $originMapping ? $originMapping->id_bodega : null;
        $bodegaDestinoId = $destMapping ? $destMapping->id_bodega : null;

        if (!empty($originLocationId) && !empty($destLocationId)) {
            if (!$bodegaOrigenId || !$bodegaDestinoId) {
                ShopifyHelper::log("inventory_transfers: [IGNORADO] Una o ambas ubicaciones de Shopify no están mapeadas a bodegas en SmartPyme", [
                    'origin_location_id' => $originLocationId,
                    'bodega_origen_id' => $bodegaOrigenId,
                    'dest_location_id' => $destLocationId,
                    'bodega_destino_id' => $bodegaDestinoId,
                    'transfer_id' => $transferId,
                ], 'warning');
                return response()->json(['status' => 'ignored', 'message' => 'Ubicaciones no mapeadas a bodegas en SmartPyme'], 200);
            }

            if ($bodegaOrigenId === $bodegaDestinoId) {
                ShopifyHelper::log("inventory_transfers: [OMITIDO] Bodega de origen y destino son iguales en SmartPyme ({$bodegaOrigenId})", [
                    'transfer_id' => $transferId,
                ]);
                return response()->json(['status' => 'ignored', 'message' => 'Misma bodega de origen y destino'], 200);
            }
        } elseif (!empty($originLocationId) && empty($destLocationId)) {
            if (!$bodegaOrigenId) {
                ShopifyHelper::log("inventory_transfers: [IGNORADO] Ubicación de origen de Shopify no está mapeada a bodega en SmartPyme", [
                    'origin_location_id' => $originLocationId,
                    'transfer_id' => $transferId,
                ], 'warning');
                return response()->json(['status' => 'ignored', 'message' => 'Ubicaciones no mapeadas a bodegas en SmartPyme'], 200);
            }
        } elseif (empty($originLocationId) && !empty($destLocationId)) {
            if (!$bodegaDestinoId) {
                ShopifyHelper::log("inventory_transfers: [IGNORADO] Ubicación de destino de Shopify no está mapeada a bodega en SmartPyme", [
                    'dest_location_id' => $destLocationId,
                    'transfer_id' => $transferId,
                ], 'warning');
                return response()->json(['status' => 'ignored', 'message' => 'Ubicaciones no mapeadas a bodegas en SmartPyme'], 200);
            }
        }

        if (empty($lineItems)) {
            ShopifyHelper::log("inventory_transfers: [IGNORADO] Sin line_items en el traslado", ['transfer_id' => $transferId], 'warning');
            return response()->json(['status' => 'ignored', 'message' => 'No line items in transfer'], 200);
        }

        // 4. Procesar los line_items transferidos
        $trasladosCreados = 0;
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            foreach ($lineItems as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                // Resolver producto en SmartPyme
                $itemInvId = !empty($item['inventory_item_id']) ? preg_replace('/[^0-9]/', '', (string)$item['inventory_item_id']) : null;
                $itemVariantId = !empty($item['variant_id']) ? preg_replace('/[^0-9]/', '', (string)$item['variant_id']) : null;
                $itemProdId = !empty($item['product_id']) ? preg_replace('/[^0-9]/', '', (string)$item['product_id']) : null;

                $producto = null;
                if ($itemInvId) {
                    $producto = Producto::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->where('shopify_inventory_item_id', $itemInvId)
                        ->first();
                }

                if (!$producto && $itemVariantId) {
                    $producto = Producto::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->where('shopify_variant_id', $itemVariantId)
                        ->first();
                }

                if (!$producto && $itemProdId) {
                    $producto = Producto::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->where('shopify_product_id', $itemProdId)
                        ->first();
                }

                if (!$producto) {
                    ShopifyHelper::log("inventory_transfers: Producto no vinculado para line_item", [
                        'inventory_item_id' => $itemInvId,
                        'variant_id' => $itemVariantId,
                        'product_id' => $itemProdId,
                    ], 'warning');
                    continue;
                }

                // Evitar ciclos en el observer al actualizar inventario desde Shopify
                $producto->syncing_from_shopify = true;

                if ($bodegaOrigenId && $bodegaDestinoId) {
                    // --- CASO 1: Traslado formal entre 2 bodegas ---
                    $invOrigen = Inventario::firstOrCreate(
                        ['id_producto' => $producto->id, 'id_bodega' => $bodegaOrigenId],
                        ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                    );

                    $invDestino = Inventario::firstOrCreate(
                        ['id_producto' => $producto->id, 'id_bodega' => $bodegaDestinoId],
                        ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                    );

                    // Si un webhook inventory_levels/update previo ya había creado un "Ajuste de inventario desde Shopify"
                    // espurio debido a la condición de carrera antes de que procesáramos el traslado,
                    // revertimos ese ajuste para que el Kardex y stock queden asentados formalmente como traslado.
                    $ajusteReciente = Kardex::where('id_producto', $producto->id)
                        ->where('id_inventario', $bodegaDestinoId)
                        ->where('detalle', 'Ajuste de inventario desde Shopify')
                        ->where('created_at', '>=', now()->subMinutes(15))
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($ajusteReciente && (float)$ajusteReciente->entrada_cantidad === (float)$qty) {
                        ShopifyHelper::log("inventory_transfers: Reemplazando ajuste espurio previo por traslado formal", [
                            'kardex_id' => $ajusteReciente->id,
                            'producto_id' => $producto->id,
                            'bodega_destino_id' => $bodegaDestinoId,
                        ]);
                        $invDestino->stock -= $qty;
                        $ajusteReciente->delete();
                    }

                    // Crear el registro formal de Traslado en SmartPyme
                    $traslado = \App\Models\Inventario\Traslado::create([
                        'id_producto' => $producto->id,
                        'id_bodega_de' => $bodegaOrigenId,
                        'id_bodega' => $bodegaDestinoId,
                        'cantidad' => $qty,
                        'costo' => $producto->costo ?? 0,
                        'id_empresa' => $empresa->id,
                        'id_usuario' => $usuario->id,
                        'concepto' => "Traslado {$transferName} de Shopify #{$conceptoReferencia}",
                        'estado' => 'Confirmado',
                    ]);

                    // 1. Salida en origen
                    $invOrigen->stock -= $qty;
                    $invOrigen->save();
                    $invOrigen->kardex($traslado, $qty * -1, $producto->precio, $producto->costo, null, ['origen' => 'shopify']);

                    // 2. Entrada en destino
                    $invDestino->stock += $qty;
                    $invDestino->save();
                    $invDestino->kardex($traslado, $qty, $producto->precio, $producto->costo, null, ['origen' => 'shopify']);

                    // Actualizar snapshots de caché
                    $this->cache->saveInventorySnapshot($invOrigen->fresh(), $producto->id);
                    $this->cache->saveInventorySnapshot($invDestino->fresh(), $producto->id);

                    ShopifyHelper::log("inventory_transfers: Traslado procesado exitosamente", [
                        'traslado_id' => $traslado->id,
                        'producto_id' => $producto->id,
                        'cantidad' => $qty,
                        'bodega_origen_id' => $bodegaOrigenId,
                        'bodega_destino_id' => $bodegaDestinoId,
                        'stock_origen_nuevo' => $invOrigen->stock,
                        'stock_destino_nuevo' => $invDestino->stock,
                    ]);

                } elseif ($bodegaOrigenId && !$bodegaDestinoId) {
                    // --- CASO 2: Transferencia de salida sin destino -> Ajuste de salida de inventario ---
                    $invOrigen = Inventario::firstOrCreate(
                        ['id_producto' => $producto->id, 'id_bodega' => $bodegaOrigenId],
                        ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                    );

                    $invOrigen->stock -= $qty;
                    $invOrigen->save();
                    $invOrigen->kardex($producto, -$qty, $producto->precio, $producto->costo, null, [
                        'origen' => 'shopify',
                        'detalle_personalizado' => "Ajuste de salida desde shopify (Transferencia {$transferName})",
                        'referencia' => $conceptoReferencia,
                        'id_usuario' => $usuario->id,
                    ]);

                    $this->cache->saveInventorySnapshot($invOrigen->fresh(), $producto->id);

                    ShopifyHelper::log("inventory_transfers: Ajuste de salida procesado exitosamente (sin destino)", [
                        'producto_id' => $producto->id,
                        'cantidad' => $qty,
                        'bodega_origen_id' => $bodegaOrigenId,
                        'stock_origen_nuevo' => $invOrigen->stock,
                        'transfer_id' => $transferId,
                    ]);

                } elseif (!$bodegaOrigenId && $bodegaDestinoId) {
                    // --- CASO 3: Transferencia de entrada sin origen -> Ajuste de entrada de inventario ---
                    $invDestino = Inventario::firstOrCreate(
                        ['id_producto' => $producto->id, 'id_bodega' => $bodegaDestinoId],
                        ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                    );

                    $invDestino->stock += $qty;
                    $invDestino->save();
                    $invDestino->kardex($producto, $qty, $producto->precio, $producto->costo, null, [
                        'origen' => 'shopify',
                        'detalle_personalizado' => "Ajuste de entrada desde shopify (Transferencia {$transferName})",
                        'referencia' => $conceptoReferencia,
                        'id_usuario' => $usuario->id,
                    ]);

                    $this->cache->saveInventorySnapshot($invDestino->fresh(), $producto->id);

                    ShopifyHelper::log("inventory_transfers: Ajuste de entrada procesado exitosamente (sin origen)", [
                        'producto_id' => $producto->id,
                        'cantidad' => $qty,
                        'bodega_destino_id' => $bodegaDestinoId,
                        'stock_destino_nuevo' => $invDestino->stock,
                        'transfer_id' => $transferId,
                    ]);
                }

                $trasladosCreados++;
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Traslado procesado exitosamente. {$trasladosCreados} productos trasladados.",
                'transfer_id' => $transferId,
            ], 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            ShopifyHelper::log("inventory_transfers: Error procesando traslado: " . $e->getMessage(), [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar traslado de Shopify',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consulta los detalles completos de un InventoryTransfer mediante la API GraphQL de Shopify.
     * Requerido porque los webhooks de transferencias en Shopify son ligeros y no incluyen los line_items.
     */
    private function obtenerDetallesTrasladoShopify($empresa, $transferId)
    {
        if (empty($empresa->shopify_store_url)) {
            return null;
        }

        $tokenService = app(\App\Services\ShopifyTokenService::class);
        $token = $tokenService->getAccessToken($empresa);

        if (!$token) {
            ShopifyHelper::log("No se pudo obtener token de acceso para consultar traslado", [
                'empresa_id' => $empresa->id,
                'transfer_id' => $transferId,
            ], 'error');
            return null;
        }

        $endpoint = rtrim($empresa->shopify_store_url, '/') . '/admin/api/2024-01/graphql.json';
        $numericId = preg_replace('/[^0-9]/', '', (string)$transferId);
        $gid = "gid://shopify/InventoryTransfer/{$numericId}";

        $query = 'query getTransfer($id: ID!) {
          node(id: $id) {
            ... on InventoryTransfer {
              id
              name
              status
              origin {
                name
                location {
                  id
                  name
                }
              }
              destination {
                name
                location {
                  id
                  name
                }
              }
              lineItems(first: 50) {
                edges {
                  node {
                    id
                    title
                    totalQuantity
                    shippedQuantity
                    inventoryItem {
                      id
                    }
                  }
                }
              }
            }
          }
        }';

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($endpoint, [
                'query' => $query,
                'variables' => ['id' => $gid]
            ]);

            if (!$response->successful()) {
                ShopifyHelper::log("Error HTTP al consultar traslado en GraphQL", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'transfer_id' => $transferId,
                ], 'error');
                return null;
            }

            $node = $response->json('data.node');
            if (!$node) {
                ShopifyHelper::log("Traslado no encontrado en Shopify GraphQL", [
                    'transfer_id' => $transferId,
                    'response' => $response->json(),
                ], 'warning');
                return null;
            }

            return $node;
        } catch (\Exception $e) {
            ShopifyHelper::log("Excepción al consultar traslado en Shopify GraphQL: " . $e->getMessage(), [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ], 'error');
            return null;
        }
    }

    private function actualizarProductoExistente($producto, $productoData, $usuario)
    {
        $stock = $productoData['_stock'] ?? null;
        $inventoryItemId = $productoData['shopify_inventory_item_id'] ?? $producto->shopify_inventory_item_id;

        // Limpiar datos especiales del array.
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal']);

        // NO marcar syncing_from_shopify para webhooks - solo para importaciones masivas
        $productoData['last_shopify_sync'] = now();

        // SKU de Shopify: se guarda tal cual en codigo y shopify_sku
        $productoData['shopify_sku'] = !empty($productoData['codigo']) ? $productoData['codigo'] : null;

        $producto->update($productoData);

        // España Dev: sincronizar inventario respetando sucursales si la empresa tiene mapeos en shopify_locations
        $empresa = Empresa::find($producto->id_empresa);
        if ($empresa && $inventoryItemId) {
            $this->sincronizarStockDesdeShopifyMultiSucursal(
                $producto,
                $inventoryItemId,
                $empresa,
                $usuario,
                $stock ?? 0,
                'actualizacion_shopify'
            );
        }
    }


    private function crearNuevoProducto($productoData, $usuario, $request, $variantImageId = null)
    {
        // Extraer datos especiales que no van al modelo
        $stock = $productoData['_stock'] ?? 0;
        $idUsuario = $productoData['_id_usuario'] ?? $usuario->id;
        $idSucursal = $productoData['_id_sucursal'] ?? $usuario->id_sucursal;
        $inventoryItemId = $productoData['shopify_inventory_item_id'] ?? null;
        
        // Limpiar datos especiales del array
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal'], $productoData['shopify_variant_image_id']);
        
        // NO marcar syncing_from_shopify para webhooks - solo para importaciones masivas
        $productoData['last_shopify_sync'] = now();

        // SKU de Shopify: se guarda tal cual en codigo y shopify_sku
        $productoData['shopify_sku'] = !empty($productoData['codigo']) ? $productoData['codigo'] : null;
        
        $producto = Producto::create($productoData);
        
        // Bloquear sincronización inversa y guardar snapshot inmediatamente para prevenir disparos de observers
        $this->cache->lockSync($producto->id);
        $this->cache->saveProductSnapshot($producto);

        // España Dev: sincronizar inventario respetando las sucursales mapeadas en lugar de volcar todo a la principal
        $empresa = Empresa::find($producto->id_empresa);
        $this->sincronizarStockDesdeShopifyMultiSucursal(
            $producto,
            $inventoryItemId ?: $producto->shopify_inventory_item_id,
            $empresa,
            $usuario,
            $stock,
            'inventario_inicial'
        );

        $this->procesarImagenes($request, $producto->id, $variantImageId);
        $this->aplicarInventarioPendiente($producto, $inventoryItemId, $empresa, $usuario);

        return $producto;
    }

    /**
     * Si el único producto local de esta ficha de Shopify no tiene variante y el webhook
     * ya trae opciones reales, lo deja en stock 0 y lo desactiva. La compra sigue apuntando a él.
     */
    private function retirarProductoSimpleAlDividir($shopifyProductId, array $productosData, $empresaId, $usuario): void
    {
        $hayVarianteReal = false;
        foreach ($productosData as $row) {
            if (!empty($row['nombre_variante'])) {
                $hayVarianteReal = true;
                break;
            }
        }
        if (!$hayVarianteReal) {
            return;
        }

        $activos = Producto::where('shopify_product_id', $shopifyProductId)
            ->where('id_empresa', $empresaId)
            ->where('enable', '!=', '0')
            ->get();

        if ($activos->count() !== 1 || !empty($activos->first()->nombre_variante)) {
            return;
        }

        $producto = $activos->first();

        Cache::put("shopify_syncing_inv_{$producto->id}", true, 60);
        $producto->shopify_variant_id = null;
        $producto->shopify_inventory_item_id = null;
        $producto->shopify_sku = null;
        $producto->enable = '0';
        $producto->saveQuietly();

        $inventarios = Inventario::where('id_producto', $producto->id)->get();
        foreach ($inventarios as $inventario) {
            if ((float) $inventario->stock == 0.0) {
                continue;
            }
            $this->actualizarInventario(
                $producto->id,
                0,
                $inventario->id_bodega,
                $usuario->id,
                ['origen' => 'shopify', 'tipo' => 'division_variantes', 'stock_anterior' => $inventario->stock]
            );
        }

        ShopifyHelper::log('products/update: producto simple retirado al dividirse en variantes', [
            'producto_id' => $producto->id,
            'shopify_product_id' => $shopifyProductId,
        ]);
    }

    private function guardarInventarioPendiente($empresaId, $inventoryItemId, $locationId, $available): void
    {
        $key = "shopify_pending_levels_{$empresaId}_{$inventoryItemId}";
        $niveles = Cache::get($key, []);
        $niveles[(string) $locationId] = (int) $available;
        Cache::put($key, $niveles, 180);
    }

    /**
     * Aplica cantidades que llegaron en inventory_levels/update antes de que existiera la variante.
     * No pisa un stock que la consulta en vivo ya dejó en un valor distinto de 0.
     */
    private function aplicarInventarioPendiente(Producto $producto, $inventoryItemId, $empresa, $usuario): void
    {
        if (empty($inventoryItemId) || !$empresa) {
            return;
        }

        $key = "shopify_pending_levels_{$empresa->id}_{$inventoryItemId}";
        $niveles = Cache::pull($key);
        if (empty($niveles) || !is_array($niveles)) {
            return;
        }

        foreach ($niveles as $locationId => $available) {
            if ((int) $available <= 0) {
                continue;
            }

            $bodegaId = $usuario->id_bodega;
            if ($locationId !== '' && $locationId !== '0') {
                $mapping = ShopifyLocation::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresa->id)
                    ->where('shopify_location_id', $locationId)
                    ->first();
                if ($mapping && !empty($mapping->id_bodega)) {
                    $bodegaId = $mapping->id_bodega;
                }
            }

            if (empty($bodegaId)) {
                continue;
            }

            $stockActual = (float) (Inventario::where('id_producto', $producto->id)
                ->where('id_bodega', $bodegaId)
                ->value('stock') ?? 0);

            if ($stockActual != 0.0) {
                continue;
            }

            $this->actualizarInventario(
                $producto->id,
                (int) $available,
                $bodegaId,
                $usuario->id,
                ['origen' => 'shopify', 'tipo' => 'inventario_inicial']
            );
        }
    }

    /**
     * Sincroniza el inventario de un producto desde Shopify respetando las sucursales/bodegas mapeadas.
     * Consulta inventory_levels.json en Shopify para asignar el stock exacto de cada ubicación a su bodega correspondiente.
     */
    private function sincronizarStockDesdeShopifyMultiSucursal(
        Producto $producto,
        $inventoryItemId,
        $empresa,
        $usuario,
        $stockFallback = 0,
        $tipoMovimiento = 'inventario_inicial'
    ) {
        $ubicacionesMapeadas = ($empresa) ? ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('sincronizar_stock', true)
            ->whereNotNull('id_bodega')
            ->get() : collect();

        if ($empresa && $empresa->tieneCredencialesShopify() && $ubicacionesMapeadas->isNotEmpty() && !empty($inventoryItemId)) {
            try {
                $client = new ShopifyApiClient(
                    $empresa->shopify_store_url,
                    $empresa->shopify_consumer_secret,
                    app(ShopifyTokenService::class),
                    $empresa
                );

                $resLevels = $client->get('inventory_levels.json', [
                    'inventory_item_ids' => $inventoryItemId,
                ]);

                $levels = is_array($resLevels)
                    ? ($resLevels['body']['inventory_levels'] ?? [])
                    : (method_exists($resLevels, 'json') ? ($resLevels->json()['inventory_levels'] ?? []) : []);

                $levelsMap = [];
                foreach ($levels as $lvl) {
                    $levelsMap[(string) $lvl['location_id']] = (float) ($lvl['available'] ?? 0);
                }

                foreach ($ubicacionesMapeadas as $locMap) {
                    $locId = (string) $locMap->shopify_location_id;
                    $stockDisponible = (float) ($levelsMap[$locId] ?? 0.0);

                    $this->actualizarInventario(
                        $producto->id,
                        $stockDisponible,
                        $locMap->id_bodega,
                        $usuario->id,
                        ['origen' => 'shopify', 'tipo' => $tipoMovimiento]
                    );

                    $inv = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $locMap->id_bodega)
                        ->first();
                    if ($inv) {
                        $this->cache->saveInventorySnapshot($inv, $producto->id);
                    }
                }
                return;
            } catch (\Throwable $t) {
                Log::channel('shopify')->warning("Error en sincronizarStockDesdeShopifyMultiSucursal para producto #{$producto->id}: " . $t->getMessage());
            }
        }

        // Fallback mono-sucursal o cuando no hay mapeos configurados
        if ($tipoMovimiento === 'inventario_inicial') {
            $bodegaDestino = $usuario->id_bodega ?: ($producto->id_bodega ?: 1);
            $this->actualizarInventario(
                $producto->id,
                $stockFallback,
                $bodegaDestino,
                $usuario->id,
                ['origen' => 'shopify', 'tipo' => $tipoMovimiento]
            );

            $inv = Inventario::where('id_producto', $producto->id)
                ->where('id_bodega', $bodegaDestino)
                ->first();
            if ($inv) {
                $this->cache->saveInventorySnapshot($inv, $producto->id);
            }
        }
    }

    public function procesarImagenes($request, $productoId, $variantImageId = null)
    {
        if (Cache::has("shopify_syncing_img_{$productoId}")) {
            Log::channel('shopify')->info('ShopifyController: omitiendo procesarImagenes por sincronización activa desde SP', [
                'producto_id' => $productoId,
            ]);
            return;
        }

        $imagenes = $request->images;
        if (!is_array($imagenes) || empty($imagenes)) {
            return;
        }

        $imagenes = $this->filtrarImagenesPorVariante($imagenes, $variantImageId);
        if (empty($imagenes)) {
            return;
        }

        $this->imageService->sincronizarImagenes($productoId, $imagenes);
    }

    /**
     * Filtra las imágenes del producto dejando solo la que corresponde a la variante.
     *
     * - Si la variante tiene image_id, se usa SOLO esa imagen.
     * - Si no tiene image_id, se usan las imágenes generales del producto (sin variant_ids)
     *   o, como fallback, la primera imagen.
     */
    private function filtrarImagenesPorVariante(array $imagenes, $variantImageId): array
    {
        // Variante con imagen específica
        if (!empty($variantImageId)) {
            foreach ($imagenes as $imagen) {
                if (($imagen['id'] ?? null) == $variantImageId) {
                    return [$imagen];
                }
            }
        }

        // Sin imagen específica: imágenes generales (sin variant_ids)
        $generales = [];
        foreach ($imagenes as $imagen) {
            $variantIds = $imagen['variant_ids'] ?? [];
            if (empty($variantIds)) {
                $generales[] = $imagen;
            }
        }

        if (!empty($generales)) {
            return $generales;
        }

        // Fallback: primera imagen del producto
        return isset($imagenes[0]) ? [$imagenes[0]] : [];
    }

    private function procesarClienteCreado(Request $request, $empresa, $usuario)
    {
        // Log::info('=== PROCESANDO CLIENTE CREADO DESDE SHOPIFY ===', [
        //     'shopify_customer_id' => $request->id,
        //     'customer_email' => $request->email ?? 'N/A',
        //     'customer_name' => ($request->first_name ?? '') . ' ' . ($request->last_name ?? ''),
        //     'empresa_id' => $empresa->id,
        //     'usuario_id' => $usuario->id,
        //     'webhook_type' => 'customers/create'
        // ]);

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            $clienteData = $this->transformer->transformarClienteDesdeShopify($request->all());
            
            // Log::info('=== CLIENTE CREADO - DATOS TRANSFORMADOS ===', [
            //     'cliente_data' => $clienteData,
            //     'shopify_customer_id' => $request->id
            // ]);
            
            $cliente = $this->shopifyClienteService->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
            // Log::info('=== CLIENTE CREADO/ACTUALIZADO ===', [
            //     'cliente_id' => $cliente->id,
            //     'cliente_correo' => $cliente->correo,
            //     'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
            //     'cliente_creado' => $cliente->wasRecentlyCreated,
            //     'shopify_customer_id' => $request->id,
            //     'webhook_type' => 'customers/create'
            // ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente procesado exitosamente',
                'cliente_id' => $cliente->id
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::channel('shopify')->error("Error procesando cliente creado desde Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function procesarClienteActualizado(Request $request, $empresa, $usuario)
    {
        // Log::info('=== PROCESANDO CLIENTE ACTUALIZADO DESDE SHOPIFY ===', [
        //     'shopify_customer_id' => $request->id,
        //     'customer_email' => $request->email ?? 'N/A',
        //     'customer_name' => ($request->first_name ?? '') . ' ' . ($request->last_name ?? ''),
        //     'empresa_id' => $empresa->id,
        //     'usuario_id' => $usuario->id,
        //     'webhook_type' => 'customers/update'
        // ]);

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            $clienteData = $this->transformer->transformarClienteDesdeShopify($request->all());
            
            // Log::info('=== CLIENTE ACTUALIZADO - DATOS TRANSFORMADOS ===', [
            //     'cliente_data' => $clienteData,
            //     'shopify_customer_id' => $request->id
            // ]);
            
            $cliente = $this->shopifyClienteService->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
            // Log::info('=== CLIENTE ACTUALIZADO ===', [
            //     'cliente_id' => $cliente->id,
            //     'cliente_correo' => $cliente->correo,
            //     'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
            //     'cliente_creado' => $cliente->wasRecentlyCreated,
            //     'shopify_customer_id' => $request->id,
            //     'webhook_type' => 'customers/update'
            // ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente actualizado exitosamente',
                'cliente_id' => $cliente->id
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::channel('shopify')->error("Error procesando cliente actualizado desde Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al actualizar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            ShopifyHelper::log("Token de empresa Shopify no válido: {$tokenEmpresa}", [], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            ShopifyHelper::log("Usuario no encontrado", ['empresa_id' => $empresa->id], 'error');
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        // Verificar primero si debe crearse como cotización
        $financialStatus = $request->financial_status ?? 'pending';
        $esPagada = ($financialStatus === 'paid' || $financialStatus === 'partially_paid');
        $debeCrearCotizacion = $empresa->facturacion_electronica && !$esPagada;

        ShopifyHelper::log("orders/create: Iniciando procesamiento de orden", [
            'token_empresa' => substr($tokenEmpresa, 0, 8) . '...',
            'shopify_order_id' => $request->id ?? 'N/A',
            'order_number' => $request->order_number ?? 'N/A',
            'financial_status' => $financialStatus,
            'es_pagada' => $esPagada,
            'debe_crear_cotizacion' => $debeCrearCotizacion,
            'line_items_count' => count($request->line_items ?? []),
        ]);

        try {
            // Verificar si la orden ya fue procesada previamente
            $referenciaShopify = 'SHOPIFY-' . $request->id;
            $ventaExistente = Venta::where('referencia_shopify', $referenciaShopify)
                ->where('id_empresa', $usuario->id_empresa)
                ->first();

            if ($ventaExistente) {
                ShopifyHelper::log("orders/create: [OMITIDO] Venta duplicada detectada - orden ya procesada previamente", [
                    'shopify_order_id' => $request->id,
                    'venta_id_existente' => $ventaExistente->id,
                    'referencia_shopify' => $referenciaShopify,
                ]);

                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Orden ya procesada previamente',
                    'venta_id' => $ventaExistente->id,
                    'duplicado' => true
                ], 200);
            }

            // Verificar duplicados por webhook_id usando cache (opcional - si falla Redis/cache, continuamos)
            $webhookId = $request->header('X-Shopify-Webhook-Id');
            if ($webhookId) {
                try {
                    $cacheKey = "shopify_webhook_processed_{$webhookId}";
                    if (Cache::has($cacheKey)) {
                        Log::channel('shopify')->warning("Webhook duplicado detectado por webhook_id", [
                            'shopify_order_id' => $request->id,
                            'webhook_id' => $webhookId,
                            'referencia_shopify' => $referenciaShopify
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'mensaje' => 'Webhook ya procesado previamente',
                            'duplicado' => true
                        ], 200);
                    }

                    // Marcar webhook como procesado por 1 hora
                    Cache::put($cacheKey, true, 3600);
                } catch (\Throwable $e) {
                    // Redis/cache no disponible (ej: MISCONF) - continuar sin cache
                    // La verificación por referencia_shopify en DB previene duplicados
                    Log::channel('shopify')->warning("Cache no disponible para verificación de webhook duplicado - continuando", [
                        'error' => $e->getMessage(),
                        'shopify_order_id' => $request->id,
                    ]);
                }
            }

            DB::beginTransaction();

            // Log::info("Iniciando procesamiento de venta", [
            //     'shopify_order_id' => $request->id,
            //     'usuario_id' => $usuario->id,
            //     'empresa_id' => $usuario->id_empresa,
            //     'documento_id' => $documento->id
            // ]);

            // Mapear canal de venta según el tipo de canal de Shopify
            $canalId = $this->mapearCanalVenta($request, $usuario->id_empresa);

            // Resolver sucursal y bodega para la orden usando ShopifyLocationService
            $locationService = app(\App\Services\ShopifyLocationService::class);
            $locationMapeada = $locationService->resolverUbicacionParaOrden($empresa, $request->all());

            $idSucursalOrden = ($locationMapeada && $locationMapeada->id_sucursal) 
                ? $locationMapeada->id_sucursal 
                : $usuario->id_sucursal;

            $idBodegaOrden = ($locationMapeada && $locationMapeada->id_bodega) 
                ? $locationMapeada->id_bodega 
                : $usuario->id_bodega;

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_bodega' => $idBodegaOrden,
                'id_sucursal' => $idSucursalOrden,
                'id_canal' => $canalId
            ]);

            // Log::info("Datos del request después del merge", $request->all());

            // Verificar si hay datos de cliente válidos
            $customer = $request->customer ?? [];
            $hasValidCustomer = !empty($customer) && 
                (!empty($customer['first_name']) || !empty($customer['last_name']) || 
                 !empty($customer['email']) || !empty($customer['phone']));
            
            if ($hasValidCustomer) {
                // Transformar cliente si hay datos válidos
                $clienteData = $this->transformer->transformarCliente($request->all());
                
                // Log::info('=== PROCESANDO CLIENTE EN VENTA SHOPIFY ===', [
                //     'shopify_order_id' => $request->id ?? 'N/A',
                //     'shopify_customer_id' => $request->customer['id'] ?? 'N/A',
                //     'customer_email' => $clienteData['correo'],
                //     'customer_name' => $clienteData['nombre'] . ' ' . $clienteData['apellido'],
                //     'empresa_id' => $usuario->id_empresa,
                //     'usuario_id' => $usuario->id
                // ]);
                
                $cliente = $this->shopifyClienteService->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            } else {
                // Usar cliente "Consumidor Final" por defecto
                $cliente = $this->shopifyClienteService->obtenerClienteConsumidorFinal($usuario->id_empresa);
                
                // Log::info('=== USANDO CLIENTE CONSUMIDOR FINAL EN VENTA ===', [
                //     'shopify_order_id' => $request->id ?? 'N/A',
                //     'cliente_id' => $cliente->id,
                //     'cliente_nombre' => $cliente->nombre_completo,
                //     'empresa_id' => $usuario->id_empresa,
                //     'usuario_id' => $usuario->id
                // ]);
            }

            // Resolver documento de facturación según los datos fiscales del cliente.
            // - Cotizaciones: cualquier documento activo (solo placeholder, no emite).
            // - Facturación electrónica: FCF o CCF según los datos fiscales del cliente.
            // - Sin facturación electrónica: Ticket.
            if ($debeCrearCotizacion) {
                $documento = Documento::where('id_sucursal', $idSucursalOrden)
                    ->where('activo', true)
                    ->first();

                if (!$documento) {
                    $documento = Documento::where('id_empresa', $empresa->id)
                        ->where('activo', true)
                        ->first();
                }
            } elseif ($empresa->facturacion_electronica) {
                $documento = $this->resolverDocumentoFactura($usuario, $empresa, $cliente, $idSucursalOrden);
            } else {
                $documento = Documento::where('id_sucursal', $idSucursalOrden)
                    ->where('nombre', 'Ticket')
                    ->where('activo', true)
                    ->first();

                if (!$documento) {
                    $documento = Documento::where('id_empresa', $empresa->id)
                        ->where('nombre', 'Ticket')
                        ->where('activo', true)
                        ->first();
                }
            }

            if (!$documento) {
                DB::rollBack();
                Log::channel('shopify')->error("Ningún documento encontrado", [
                    'id_sucursal' => $idSucursalOrden,
                    'facturacion_electronica' => $empresa->facturacion_electronica,
                    'debe_crear_cotizacion' => $debeCrearCotizacion
                ]);
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Ningún documento activo encontrado para la sucursal'
                ], 500);
            }

            $request->merge(['id_documento' => $documento->id]);

            $ventaData = $this->transformer->transformarVenta(
                $request->all(),
                $cliente->id,
                $documento->id,
                $documento->correlativo
            );
            
            // Si debe crearse como cotización, marcar el campo cotizacion = 1
            if ($debeCrearCotizacion) {
                $ventaData['cotizacion'] = 1;
                Log::info("Creando cotización en lugar de venta", [
                    'shopify_order_id' => $request->id,
                    'financial_status' => $financialStatus
                ]);
            }
            
            // Log::info("Datos de la venta transformados", $ventaData);
            $venta = Venta::create($ventaData);
            
            // Log::info("Venta creada", ['venta_id' => $venta->id]);

            // Log::info($request->line_items);
            foreach ($request->line_items as $item) {
                // Validar que el item tenga los datos mínimos necesarios
                if (empty($item) || !is_array($item)) {
                    Log::warning("Line item inválido o vacío", ['item' => $item]);
                    continue;
                }

                // Log::info("Procesando line item", ['variant_id' => $item['variant_id'] ?? 'N/A', 'sku' => $item['sku'] ?? 'N/A']);
                
                $producto = null;
                
                // Buscar producto por variant_id si existe
                if (!empty($item['variant_id'])) {
                    $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                        ->where('id_empresa', $usuario->id_empresa)
                        ->first();
                }

                // Si no se encuentra por variant_id, buscar por SKU
                if (!$producto && !empty($item['sku'])) {
                    $producto = Producto::where('codigo', $item['sku'])
                        ->where('id_empresa', $usuario->id_empresa)
                        ->first();
                }

                // Si no se encuentra el producto, crearlo
                if (!$producto) {
                    $productoData = $this->transformer->transformarProducto(
                        $item,
                        $usuario->id_empresa,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                    $producto = Producto::create($productoData);
                } else {
                    // Si el producto ya existía pero no tiene vinculado shopify_variant_id, auto-vincularlo
                    if (!empty($item['variant_id']) && $producto->shopify_variant_id != $item['variant_id']) {
                        $existeConVariant = Producto::where('shopify_variant_id', $item['variant_id'])
                            ->where('id_empresa', $usuario->id_empresa)
                            ->where('id', '!=', $producto->id)
                            ->exists();
                        if (!$existeConVariant) {
                            $producto->shopify_variant_id = $item['variant_id'];
                            if (!empty($item['product_id']) && empty($producto->shopify_product_id)) {
                                $producto->shopify_product_id = $item['product_id'];
                            }
                            $producto->save();
                        }
                    }
                }

                $taxesIncluded = $request->taxes_included ?? false;
                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id, $usuario->id_empresa, $taxesIncluded);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

                // Solo actualizar inventario si NO es una cotización
                // Las cotizaciones no afectan el inventario
                if (!$debeCrearCotizacion) {
                    $stockAntes = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->value('stock') ?? 0;

                    // Actualizar inventario
                    Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->decrement('stock', $item['quantity']);

                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();

                    ShopifyHelper::log("orders/create: [DESCUENTO VENTA PAGADA] Inventario descontado por venta directa", [
                        'producto_id' => $producto->id,
                        'codigo' => $producto->codigo,
                        'nombre' => $producto->nombre,
                        'bodega_id' => $venta->id_bodega,
                        'stock_anterior' => $stockAntes,
                        'cantidad_descontada' => $item['quantity'],
                        'stock_nuevo' => $inventario ? $inventario->stock : null,
                        'shopify_order_id' => $request->id,
                        'line_item_id' => $item['id'] ?? null,
                    ]);

                    if ($inventario) {
                        $inventario->kardex($venta, $item['quantity'], $item['price'], null, null, ['origen' => 'shopify']);
                    }
                } else {
                    $stockActual = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->value('stock') ?? 0;

                    ShopifyHelper::log("orders/create: [OMITIDO POR COTIZACIÓN] No se descuenta inventario (orden pendiente creada como cotización)", [
                        'producto_id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'bodega_id' => $venta->id_bodega,
                        'cantidad_orden' => $item['quantity'],
                        'stock_actual_mantenido' => $stockActual,
                        'shopify_order_id' => $request->id,
                    ]);
                }
            }

            // Procesar tipos de envío si existen
            if (!empty($request->shipping_lines)) {
                Log::info("Procesando tipos de envío", [
                    'venta_id' => $venta->id,
                    'shipping_lines_count' => count($request->shipping_lines)
                ]);

                $detallesEnvio = $this->shippingService->procesarTiposEnvio(
                    $request->shipping_lines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );

                Log::info("Detalles de envío procesados", [
                    'venta_id' => $venta->id,
                    'detalles_creados' => count($detallesEnvio)
                ]);
            }

            // Guardar impuesto de la venta en venta_impuestos
            // Comentado: El IVA ya se guarda directamente en la venta
            // if ($venta->iva > 0) {
            //     $this->impuestosService->guardarImpuestoVenta(
            //         $venta->id,
            //         $venta->iva,
            //         $usuario->id_empresa
            //     );

            //     Log::info("Impuesto de venta guardado", [
            //         'venta_id' => $venta->id,
            //         'monto_impuesto' => $venta->iva,
            //         'empresa_id' => $usuario->id_empresa
            //     ]);
            // }

            // Solo incrementar correlativo si NO es una cotización
            // Las cotizaciones no deben asignar correlativo
            if (!$debeCrearCotizacion) {
                $documento = Documento::findOrfail($venta->id_documento);
                $documento->increment('correlativo');
                
                Log::info("Correlativo incrementado para venta", [
                    'venta_id' => $venta->id,
                    'documento_id' => $documento->id,
                    'nuevo_correlativo' => $documento->correlativo
                ]);
            } else {
                Log::info("Cotización creada - no se incrementa correlativo", [
                    'venta_id' => $venta->id,
                    'documento_id' => $venta->id_documento
                ]);
            }

            // Procesar puntos de fidelización si la venta está pagada
            if ($venta->estado == 'Pagada' && $venta->id_cliente) {
                try {
                    $consumoPuntosService = app(ConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::channel('shopify')->error('Error al procesar puntos de fidelización en Shopify', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage()
                    ]);
                    // No se interrumpe la transacción por errores en puntos
                }
            }

            DB::commit();

            $mensaje = $debeCrearCotizacion 
                ? 'Cotización procesada correctamente' 
                : 'Venta procesada correctamente';
            
            $tipoDocumento = $debeCrearCotizacion ? 'cotizacion' : 'venta';

            return response()->json([
                'status' => 'success',
                'mensaje' => $mensaje,
                'venta_id' => $venta->id,
                'tipo' => $tipoDocumento,
                'es_cotizacion' => $debeCrearCotizacion
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('shopify')->error('Error procesando venta de Shopify: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportarShopify(Request $request)
    {
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        if (
            empty($empresa->shopify_store_url) ||
            !$empresa->tieneCredencialesShopify()
        ) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'No tienes configurada la integración con Shopify'
            ], 400);
        }

        if ($empresa->shopify_status != 'connected') {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa debe estar activa con integración de Shopify'
            ], 400);
        }

        $sucursalId = $user->id_bodega;

        // Encolar el trabajo
        ExportProductsToShopify::dispatch($user->id, $sucursalId);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Exportación de productos a Shopify iniciada. Este proceso puede tomar varios minutos.'
        ]);
    }

    public function iniciarConsolidacion(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::find($user->id_empresa);
        if (!$empresa || !$empresa->tieneCredencialesShopify()) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa no tiene credenciales de Shopify configuradas o no está conectada.',
            ], 400);
        }

        $direccion = $request->input('direccion', 'shopify_to_sp');
        $opciones = [
            'vincular_sku' => filter_var($request->input('vincular_sku', true), FILTER_VALIDATE_BOOLEAN),
            'actualizar_precios' => filter_var($request->input('actualizar_precios', true), FILTER_VALIDATE_BOOLEAN),
            'actualizar_stock' => filter_var($request->input('actualizar_stock', false), FILTER_VALIDATE_BOOLEAN),
            'crear_nuevos' => filter_var($request->input('crear_nuevos', true), FILTER_VALIDATE_BOOLEAN),
        ];

        // Limpiar o inicializar estado previo en caché
        $cacheKey = "shopify_consolidacion_{$empresa->id}";
        Cache::put($cacheKey, [
            'estado' => 'procesando',
            'progreso' => 0,
            'mensaje' => 'Iniciando consolidación...',
            'total' => 0,
            'procesados' => 0,
            'vinculados' => 0,
            'actualizados' => 0,
            'creados' => 0,
            'errores' => 0,
            'direccion' => $direccion,
            'fecha_inicio' => now()->toIso8601String(),
        ], 7200);

        // Despachar Job en segundo plano
        ConsolidarProductosShopifyJob::dispatch($empresa->id, $user->id, $direccion, $opciones);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Consolidación iniciada exitosamente en segundo plano.',
            'direccion' => $direccion,
        ]);
    }

    public function obtenerEstadoConsolidacion(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $cacheKey = "shopify_consolidacion_{$user->id_empresa}";
        $estado = Cache::get($cacheKey, [
            'estado' => 'inactivo',
            'progreso' => 0,
            'mensaje' => 'No hay consolidación activa.',
            'total' => 0,
            'procesados' => 0,
            'vinculados' => 0,
            'actualizados' => 0,
            'creados' => 0,
            'errores' => 0,
        ]);

        return response()->json($estado);
    }

    /**
     * Obtiene la comparativa en tiempo real de un producto individual entre SmartPyme y Shopify.
     */
    public function obtenerComparativaProducto($id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::find($user->id_empresa);
        if (!$empresa || !$empresa->tieneCredencialesShopify()) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa no tiene credenciales de Shopify configuradas o no está conectada.',
            ], 400);
        }

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('id', $id)
            ->first();

        if (!$producto) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Producto no encontrado.',
            ], 404);
        }

        // Obtener inventarios locales con nombre de bodega
        $inventariosLocales = DB::table('inventario')
            ->join('sucursal_bodegas', 'sucursal_bodegas.id', '=', 'inventario.id_bodega')
            ->where('inventario.id_producto', $producto->id)
            ->whereNull('inventario.deleted_at')
            ->select('sucursal_bodegas.id as id_bodega', 'sucursal_bodegas.nombre as nombre_bodega', 'inventario.stock')
            ->get();

        // Mapeo de ubicaciones Shopify con bodegas de SmartPyme
        $ubicacionesMapeadas = ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->whereNotNull('id_bodega')
            ->with('bodega')
            ->get();

        $client = new ShopifyApiClient(
            $empresa->shopify_store_url,
            $empresa->shopify_consumer_secret,
            app(ShopifyTokenService::class),
            $empresa
        );

        $variantShopify = null;
        $skuBuscado = trim((string) ($producto->codigo ?: $producto->shopify_sku));

        // 1. Intentar buscar por shopify_variant_id si existe
        if (!empty($producto->shopify_variant_id)) {
            try {
                $resVar = $client->get("variants/{$producto->shopify_variant_id}.json");
                if (is_array($resVar) && !empty($resVar['body']['variant'])) {
                    $variantShopify = $resVar['body']['variant'];
                } elseif (is_object($resVar) && method_exists($resVar, 'json') && !empty($resVar->json()['variant'])) {
                    $variantShopify = $resVar->json()['variant'];
                }
            } catch (\Throwable $t) {
                Log::channel('shopify')->warning("Error consultando variante #{$producto->shopify_variant_id} en Shopify: " . $t->getMessage());
            }
        }

        // 2. Si no se encontró por ID pero tiene SKU, resolver por SKU en Shopify
        if (!$variantShopify && !empty($skuBuscado)) {
            $resolver = app(\App\Services\ShopifySkuResolver::class);
            $resolucion = $resolver->resolveBySku($client, $skuBuscado);
            if (is_array($resolucion) && !empty($resolucion['variant_id'])) {
                try {
                    $resVar = $client->get("variants/{$resolucion['variant_id']}.json");
                    if (is_array($resVar) && !empty($resVar['body']['variant'])) {
                        $variantShopify = $resVar['body']['variant'];
                    } elseif (is_object($resVar) && method_exists($resVar, 'json') && !empty($resVar->json()['variant'])) {
                        $variantShopify = $resVar->json()['variant'];
                    }
                } catch (\Throwable $t) {
                    Log::channel('shopify')->warning("Error consultando variante resuelta #{$resolucion['variant_id']}: " . $t->getMessage());
                }
            }
        }

        $encontradoEnShopify = !empty($variantShopify);
        $stockLevelsShopify = [];

        if ($encontradoEnShopify && !empty($variantShopify['inventory_item_id'])) {
            try {
                $resLevels = $client->get('inventory_levels.json', [
                    'inventory_item_ids' => $variantShopify['inventory_item_id']
                ]);
                $levels = is_array($resLevels)
                    ? ($resLevels['body']['inventory_levels'] ?? [])
                    : (method_exists($resLevels, 'json') ? ($resLevels->json()['inventory_levels'] ?? []) : []);

                foreach ($levels as $lvl) {
                    $stockLevelsShopify[(string) $lvl['location_id']] = (float) ($lvl['available'] ?? 0);
                }
            } catch (\Throwable $t) {
                Log::channel('shopify')->warning("Error consultando inventory_levels en Shopify: " . $t->getMessage());
            }
        }

        // Comparativa de stock por bodega / ubicación
        $comparativaStock = [];
        if ($ubicacionesMapeadas->isNotEmpty()) {
            foreach ($ubicacionesMapeadas as $loc) {
                $stockLocal = (float) ($inventariosLocales->firstWhere('id_bodega', $loc->id_bodega)->stock ?? 0);
                $stockShopify = $encontradoEnShopify
                    ? (float) ($stockLevelsShopify[(string) $loc->shopify_location_id] ?? 0.0)
                    : null;

                $diferencia = ($stockShopify !== null) ? round($stockShopify - $stockLocal, 2) : null;

                $comparativaStock[] = [
                    'id_bodega' => $loc->id_bodega,
                    'nombre_bodega' => $loc->bodega->nombre ?? "Bodega #{$loc->id_bodega}",
                    'shopify_location_id' => $loc->shopify_location_id,
                    'nombre_ubicacion_shopify' => $loc->shopify_location_name ?? "Ubicación #{$loc->shopify_location_id}",
                    'stock_local' => $stockLocal,
                    'stock_shopify' => $stockShopify,
                    'diferencia' => $diferencia,
                    'coincide' => ($diferencia === 0.0),
                ];
            }
        }

        // Si no hay ubicaciones mapeadas, armar fila con la bodega principal
        if (empty($comparativaStock) && $inventariosLocales->isNotEmpty()) {
            foreach ($inventariosLocales as $inv) {
                $stockShopify = isset($stockLevelsShopify[(string) $empresa->shopify_location_id])
                    ? (float) $stockLevelsShopify[(string) $empresa->shopify_location_id]
                    : ($encontradoEnShopify ? (float) ($variantShopify['inventory_quantity'] ?? 0) : null);

                $diferencia = ($stockShopify !== null) ? round($stockShopify - (float) $inv->stock, 2) : null;

                $comparativaStock[] = [
                    'id_bodega' => $inv->id_bodega,
                    'nombre_bodega' => $inv->nombre_bodega,
                    'shopify_location_id' => $empresa->shopify_location_id,
                    'nombre_ubicacion_shopify' => 'Ubicación Predeterminada',
                    'stock_local' => (float) $inv->stock,
                    'stock_shopify' => $stockShopify,
                    'diferencia' => $diferencia,
                    'coincide' => ($diferencia === 0.0),
                ];
            }
        }

        $precioLocal = (float) (($producto->precio_con_iva > 0) ? $producto->precio_con_iva : $producto->precio);
        $precioShopify = $encontradoEnShopify ? (float) ($variantShopify['price'] ?? 0) : null;
        $precioDifiere = ($precioShopify !== null) ? (abs($precioLocal - $precioShopify) > 0.009) : null;

        return response()->json([
            'status' => 'success',
            'vinculado' => !empty($producto->shopify_variant_id),
            'encontrado_en_shopify' => $encontradoEnShopify,
            'local' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'nombre_variante' => $producto->nombre_variante,
                'codigo' => $producto->codigo,
                'barcode' => $producto->barcode,
                'precio' => (float) $producto->precio,
                'precio_sin_iva' => (float) $producto->precio_sin_iva,
                'precio_con_iva' => (float) $producto->precio_con_iva,
                'shopify_product_id' => $producto->shopify_product_id,
                'shopify_variant_id' => $producto->shopify_variant_id,
                'shopify_sku' => $producto->shopify_sku,
                'stock_total' => (float) $inventariosLocales->sum('stock'),
            ],
            'shopify' => $encontradoEnShopify ? [
                'product_id' => $variantShopify['product_id'] ?? null,
                'variant_id' => $variantShopify['id'] ?? null,
                'title' => $variantShopify['title'] ?? null,
                'sku' => $variantShopify['sku'] ?? null,
                'barcode' => $variantShopify['barcode'] ?? null,
                'price' => $precioShopify,
                'inventory_item_id' => $variantShopify['inventory_item_id'] ?? null,
                'inventory_quantity' => (float) ($variantShopify['inventory_quantity'] ?? 0),
            ] : null,
            'precio_difiere' => $precioDifiere,
            'comparativa_stock' => $comparativaStock,
        ]);
    }

    /**
     * Sincroniza un producto individual entre SmartPyme y Shopify en la dirección seleccionada.
     */
    public function sincronizarProductoIndividual($id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::find($user->id_empresa);
        if (!$empresa || !$empresa->tieneCredencialesShopify()) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa no tiene credenciales de Shopify configuradas o no está conectada.',
            ], 400);
        }

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('id', $id)
            ->first();

        if (!$producto) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Producto no encontrado.',
            ], 404);
        }

        $direccion = $request->input('direccion', 'shopify_to_sp'); // 'shopify_to_sp' o 'sp_to_shopify'
        $syncPrecio = filter_var($request->input('sincronizar_precio', true), FILTER_VALIDATE_BOOLEAN);
        $syncStock = filter_var($request->input('sincronizar_stock', true), FILTER_VALIDATE_BOOLEAN);
        $syncImagenes = filter_var($request->input('sincronizar_imagenes', false), FILTER_VALIDATE_BOOLEAN);
        $idBodega = $request->input('id_bodega', 'todas') ?: 'todas';

        $client = new ShopifyApiClient(
            $empresa->shopify_store_url,
            $empresa->shopify_consumer_secret,
            app(ShopifyTokenService::class),
            $empresa
        );

        if ($direccion === 'shopify_to_sp') {
            // SHOPIFY -> SMARTPYME
            $variantShopify = null;
            if (!empty($producto->shopify_variant_id)) {
                $res = $client->get("variants/{$producto->shopify_variant_id}.json");
                $variantShopify = is_array($res) ? ($res['body']['variant'] ?? null) : ($res->json()['variant'] ?? null);
            }

            if (!$variantShopify) {
                $skuBuscado = trim((string) ($producto->codigo ?: $producto->shopify_sku));
                if ($skuBuscado !== '') {
                    $resolucion = app(\App\Services\ShopifySkuResolver::class)->resolveBySku($client, $skuBuscado);
                    if (is_array($resolucion) && !empty($resolucion['variant_id'])) {
                        $res = $client->get("variants/{$resolucion['variant_id']}.json");
                        $variantShopify = is_array($res) ? ($res['body']['variant'] ?? null) : ($res->json()['variant'] ?? null);
                    }
                }
            }

            if (!$variantShopify) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'No se encontró la variante correspondiente en Shopify para sincronizar.',
                ], 404);
            }

            // Vincular IDs
            $producto->shopify_product_id = $variantShopify['product_id'] ?? $producto->shopify_product_id;
            $producto->shopify_variant_id = $variantShopify['id'];
            $producto->shopify_inventory_item_id = $variantShopify['inventory_item_id'] ?? $producto->shopify_inventory_item_id;
            if (!empty($variantShopify['sku'])) {
                $producto->shopify_sku = $variantShopify['sku'];
            }

            // Sincronizar precio si aplica
            if ($syncPrecio && isset($variantShopify['price'])) {
                $precioShopify = (float) $variantShopify['price'];
                $producto->precio = $precioShopify;
                $producto->precio_con_iva = $precioShopify;
                $impuestosService = app(\App\Services\ImpuestosService::class);
                $producto->precio_sin_iva = $impuestosService->calcularPrecioSinImpuesto($precioShopify, $empresa->id);
            }

            $producto->saveQuietly();

            // Sincronizar stock si aplica
            if ($syncStock && !empty($variantShopify['inventory_item_id'])) {
                $ubicacionesMapeadas = ShopifyLocation::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresa->id)
                    ->where('sincronizar_stock', true)
                    ->whereNotNull('id_bodega')
                    ->get();

                // Consultar niveles de inventario en Shopify para este item
                $levelsMap = [];
                try {
                    $resLevels = $client->get('inventory_levels.json', [
                        'inventory_item_ids' => $variantShopify['inventory_item_id'],
                    ]);
                    $levels = is_array($resLevels) ? ($resLevels['body']['inventory_levels'] ?? []) : ($resLevels->json()['inventory_levels'] ?? []);
                    foreach ($levels as $lvl) {
                        $levelsMap[(string) $lvl['location_id']] = (float) ($lvl['available'] ?? 0);
                    }
                } catch (\Throwable $t) {
                    Log::channel('shopify')->warning("Error consultando inventory_levels en sincronización individual: " . $t->getMessage());
                }

                Cache::put("shopify_syncing_inv_{$producto->id}", true, 60);
                try {
                    if (!empty($idBodega) && $idBodega !== 'todas') {
                        // Sincronizar una sola bodega específica
                        $locMap = $ubicacionesMapeadas->firstWhere('id_bodega', (int) $idBodega);
                        $locId = $locMap ? (string) $locMap->shopify_location_id : (string) $empresa->shopify_location_id;
                        $stockDisponible = isset($levelsMap[$locId]) ? $levelsMap[$locId] : 0.0;

                        $inv = Inventario::withoutEvents(function () use ($producto, $idBodega, $stockDisponible) {
                            return Inventario::updateOrCreate(
                                ['id_producto' => $producto->id, 'id_bodega' => (int) $idBodega],
                                ['stock' => $stockDisponible]
                            );
                        });

                        $delta = $stockDisponible - ($inv->wasRecentlyCreated ? 0 : $inv->getOriginal('stock'));
                        if (abs($delta) > 0.001) {
                            $inv->kardex(
                                $producto,
                                $delta,
                                $producto->precio,
                                $producto->costo,
                                null,
                                ['origen' => 'shopify', 'observacion' => 'Ajuste individual desde Shopify']
                            );
                        }
                    } else {
                        // Sincronizar TODAS las sucursales mapeadas (1 a 1)
                        if ($ubicacionesMapeadas->isNotEmpty()) {
                            foreach ($ubicacionesMapeadas as $locMap) {
                                $locId = (string) $locMap->shopify_location_id;
                                $stockDisponible = (float) ($levelsMap[$locId] ?? 0.0);

                                $inv = Inventario::withoutEvents(function () use ($producto, $locMap, $stockDisponible) {
                                    return Inventario::updateOrCreate(
                                        ['id_producto' => $producto->id, 'id_bodega' => $locMap->id_bodega],
                                        ['stock' => $stockDisponible]
                                    );
                                });

                                $delta = $stockDisponible - ($inv->wasRecentlyCreated ? 0 : $inv->getOriginal('stock'));
                                if (abs($delta) > 0.001) {
                                    $inv->kardex(
                                        $producto,
                                        $delta,
                                        $producto->precio,
                                        $producto->costo,
                                        null,
                                        ['origen' => 'shopify', 'observacion' => "Ajuste individual desde Shopify ({$locMap->shopify_location_name})"]
                                    );
                                }
                            }
                        } else {
                            // Fallback mono-sucursal sin mapeo
                            $stockDisponible = (float) ($variantShopify['inventory_quantity'] ?? 0);
                            $bodegaDestino = $user->id_bodega ?: $producto->id_bodega;
                            if ($bodegaDestino) {
                                $inv = Inventario::withoutEvents(function () use ($producto, $bodegaDestino, $stockDisponible) {
                                    return Inventario::updateOrCreate(
                                        ['id_producto' => $producto->id, 'id_bodega' => $bodegaDestino],
                                        ['stock' => $stockDisponible]
                                    );
                                });

                                $delta = $stockDisponible - ($inv->wasRecentlyCreated ? 0 : $inv->getOriginal('stock'));
                                if (abs($delta) > 0.001) {
                                    $inv->kardex(
                                        $producto,
                                        $delta,
                                        $producto->precio,
                                        $producto->costo,
                                        null,
                                        ['origen' => 'shopify', 'observacion' => 'Ajuste individual desde Shopify']
                                    );
                                }
                            }
                        }
                    }
                } finally {
                    Cache::forget("shopify_syncing_inv_{$producto->id}");
                }
            }

            // Sincronizar imágenes desde Shopify si aplica
            if ($syncImagenes && !empty($producto->shopify_product_id)) {
                try {
                    $resProd = $client->get("products/{$producto->shopify_product_id}.json");
                    $prodShopify = is_array($resProd) ? ($resProd['body']['product'] ?? null) : ($resProd->json()['product'] ?? null);
                    if ($prodShopify && !empty($prodShopify['images'])) {
                        $this->imageService->sincronizarImagenes($producto->id, $prodShopify['images']);
                    }
                } catch (\Throwable $t) {
                    Log::channel('shopify')->warning("Error sincronizando imágenes desde Shopify en sincronización individual: " . $t->getMessage());
                }
            }

            return response()->json([
                'status' => 'success',
                'mensaje' => "Producto #{$producto->id} actualizado exitosamente desde Shopify.",
            ]);
        } else {
            // SMARTPYME -> SHOPIFY
            $this->cache->lockSync($producto->id);

            // Si el producto no tiene shopify_variant_id, intentar resolver o crearlo
            if (empty($producto->shopify_variant_id)) {
                $skuBuscado = trim((string) ($producto->codigo ?: $producto->shopify_sku));
                if ($skuBuscado !== '') {
                    $resolucion = app(\App\Services\ShopifySkuResolver::class)->resolveBySku($client, $skuBuscado);
                    if (is_array($resolucion) && !empty($resolucion['variant_id'])) {
                        $producto->shopify_product_id = $resolucion['product_id'] ?? null;
                        $producto->shopify_variant_id = $resolucion['variant_id'];
                        $producto->shopify_inventory_item_id = $resolucion['inventory_item_id'] ?? null;
                        $producto->shopify_sku = $skuBuscado;
                        $producto->saveQuietly();
                    }
                }

                if (empty($producto->shopify_variant_id)) {
                    // Exportar/crear como nuevo producto en Shopify
                    $exportService = app(\App\Services\ShopifyExportService::class);
                    $bodegaExport = (!empty($idBodega) && $idBodega !== 'todas') ? (int) $idBodega : null;
                    $resExport = $exportService->exportarProductos($user, collect([$producto]), $bodegaExport);
                    if (($resExport['errores'] ?? 0) > 0) {
                        return response()->json([
                            'status' => 'error',
                            'mensaje' => 'No se pudo crear el producto en Shopify: ' . json_encode($resExport['detalles'] ?? []),
                        ], 500);
                    }
                    $producto->refresh();
                }
            }

            if (empty($producto->shopify_variant_id)) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'El producto no está vinculado con Shopify y no se pudo enlazar.',
                ], 400);
            }

            // Sincronizar precio hacia Shopify
            if ($syncPrecio) {
                $precioVenta = ($producto->precio_con_iva > 0) ? $producto->precio_con_iva : $producto->precio;
                $payload = [
                    'variant' => [
                        'id' => (int) $producto->shopify_variant_id,
                        'price' => number_format((float) $precioVenta, 2, '.', ''),
                    ]
                ];
                if (!empty($producto->codigo)) {
                    $payload['variant']['sku'] = $producto->codigo;
                }
                try {
                    $client->put("variants/{$producto->shopify_variant_id}.json", $payload);
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                        // Variante eliminada en Shopify, desvincular y recrear
                        $producto->shopify_variant_id = null;
                        $producto->shopify_inventory_item_id = null;
                        $producto->shopify_product_id = null;
                        $producto->saveQuietly();

                        $exportService = app(\App\Services\ShopifyExportService::class);
                        $bodegaExport = (!empty($idBodega) && $idBodega !== 'todas') ? (int) $idBodega : null;
                        $resExport = $exportService->exportarProductos($user, collect([$producto]), $bodegaExport);
                        if (($resExport['errores'] ?? 0) > 0) {
                            return response()->json([
                                'status' => 'error',
                                'mensaje' => 'La variante en Shopify fue eliminada y no se pudo recrear: ' . json_encode($resExport['detalles'] ?? []),
                            ], 500);
                        }
                        $producto->refresh();
                    } else {
                        throw $e;
                    }
                }
            }

            // Sincronizar stock hacia Shopify (multi-sucursal o mono-sucursal)
            if ($syncStock && !empty($producto->shopify_inventory_item_id)) {
                $ubicacionesMapeadas = ShopifyLocation::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresa->id)
                    ->where('sincronizar_stock', true)
                    ->whereNotNull('id_bodega')
                    ->get();

                if (!empty($idBodega) && $idBodega !== 'todas') {
                    // Solo una bodega específica
                    $locMap = $ubicacionesMapeadas->firstWhere('id_bodega', (int) $idBodega);
                    $locId = $locMap ? $locMap->shopify_location_id : ($empresa->shopify_location_id ?: null);

                    if ($locId) {
                        $stockBodega = DB::table('inventario')
                            ->where('id_producto', $producto->id)
                            ->where('id_bodega', (int) $idBodega)
                            ->whereNull('deleted_at')
                            ->value('stock') ?? 0;

                        $this->setInventoryLevelWithTracking($client, (int) $locId, (int) $producto->shopify_inventory_item_id, $stockBodega);
                    }
                } else {
                    // Sincronizar TODAS las sucursales mapeadas (1 a 1)
                    if ($ubicacionesMapeadas->isNotEmpty()) {
                        foreach ($ubicacionesMapeadas as $locMap) {
                            $stockBodega = DB::table('inventario')
                                ->where('id_producto', $producto->id)
                                ->where('id_bodega', $locMap->id_bodega)
                                ->whereNull('deleted_at')
                                ->value('stock') ?? 0;

                            $this->setInventoryLevelWithTracking($client, (int) $locMap->shopify_location_id, (int) $producto->shopify_inventory_item_id, $stockBodega);
                        }
                    } else {
                        // Fallback ubicación por defecto (total local)
                        $locId = $empresa->shopify_location_id ?: null;
                        if ($locId) {
                            $stockTotal = DB::table('inventario')
                                ->where('id_producto', $producto->id)
                                ->whereNull('deleted_at')
                                ->sum('stock') ?? 0;

                            $this->setInventoryLevelWithTracking($client, (int) $locId, (int) $producto->shopify_inventory_item_id, $stockTotal);
                        }
                    }
                }
            }

            // Sincronizar imágenes hacia Shopify si aplica
            if ($syncImagenes && !empty($producto->shopify_product_id)) {
                $this->imageService->sincronizarImagenesHaciaShopify($producto, $client);
            }

            return response()->json([
                'status' => 'success',
                'mensaje' => "Producto #{$producto->id} sincronizado exitosamente hacia Shopify.",
            ]);
        }
    }

    /**
     * Establece el nivel de inventario en Shopify asegurando que el seguimiento esté activo.
     */
    private function setInventoryLevelWithTracking($client, int $locationId, int $inventoryItemId, $available)
    {
        try {
            return $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available' => (int) round((float) $available),
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'tracking enabled') || str_contains($e->getMessage(), 'inventory tracking')) {
                try {
                    $client->put("inventory_items/{$inventoryItemId}.json", [
                        'inventory_item' => [
                            'id' => $inventoryItemId,
                            'tracked' => true,
                        ]
                    ]);
                    return $client->post('inventory_levels/set.json', [
                        'location_id' => $locationId,
                        'inventory_item_id' => $inventoryItemId,
                        'available' => (int) round((float) $available),
                    ]);
                } catch (\Throwable $t2) {
                    Log::channel('shopify')->warning("No se pudo activar tracking para item #{$inventoryItemId}: " . $t2->getMessage());
                }
            }
            throw $e;
        }
    }

    private function buscarCategoria($nombre, $id_empresa)
    {
        $categoria = Categoria::where('nombre', $nombre)
            ->where('id_empresa', $id_empresa)
            ->first();

        if (!$categoria) {
            $categoria = Categoria::create([
                'nombre' => $nombre,
                'id_empresa' => $id_empresa,
                'enable' => 1,
                'descripcion' => 'Categoria generada desde Shopify',
            ]);
        }

        return $categoria;
    }

    //actualizar inventario
    private function actualizarInventario($productoId, $cantidad, $bodegaId, $usuarioId, $opciones = [])
    {
        $esDesdeShopify = !empty($opciones['origen']) && $opciones['origen'] === 'shopify';

        // BUG-1 fix: señalizar la sincronización en cache (TTL 60s) en lugar de en BD.
        // Con la BD: si el proceso muere entre set true y set false, el flag queda atascado
        // en true indefinidamente y el observer bloquea toda sincronización futura.
        // Con cache TTL: la clave expira automáticamente aunque el proceso muera.
        if ($esDesdeShopify) {
            Cache::put("shopify_syncing_inv_{$productoId}", true, 60);
        }

        try {
            ShopifyHelper::log("actualizarInventario: Ejecutando ajuste", [
                'producto_id' => $productoId,
                'cantidad_stock_nuevo' => $cantidad,
                'bodega_id' => $bodegaId,
                'usuario_id' => $usuarioId,
                'opciones' => $opciones,
                'es_desde_shopify' => $esDesdeShopify,
            ]);

            $inventario = Inventario::where('id_producto', $productoId)
                ->where('id_bodega', $bodegaId)
                ->first();

            if ($inventario) {
                $stockAnterior = $inventario->stock;
                $delta = $cantidad - ($opciones['stock_anterior'] ?? $stockAnterior);

                ShopifyHelper::log("actualizarInventario: Inventario existente encontrado, actualizando stock", [
                    'inventario_id' => $inventario->id,
                    'producto_id' => $productoId,
                    'bodega_id' => $bodegaId,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $cantidad,
                    'delta' => $delta,
                    'origen' => $opciones['origen'] ?? 'local',
                ]);

                $inventario->update([
                    'stock' => $cantidad
                ]);
                $producto = Producto::find($productoId);

                $esDesdeShopify = !empty($opciones['origen']) && $opciones['origen'] === 'shopify';
                $stockAnteriorOp = $opciones['stock_anterior'] ?? $stockAnterior;

                if ($esDesdeShopify && $producto) {
                    $deltaKardex = $cantidad - $stockAnteriorOp;
                    if ($deltaKardex != 0) {
                        ShopifyHelper::log("actualizarInventario: Registrando movimiento en kardex desde Shopify", [
                            'producto_id' => $productoId,
                            'delta_kardex' => $deltaKardex,
                            'stock_nuevo' => $cantidad,
                        ]);
                        $inventario->kardex($producto, $deltaKardex, $producto->precio, $producto->costo, null, [
                            'origen' => 'shopify',
                            'id_usuario' => $usuarioId,
                        ]);
                    }
                } elseif ($inventario->stock > 0 && $producto) {
                    $producto->id_usuario = $usuarioId;
                    $inventario->kardex($producto, 0, $producto->precio, $producto->costo);
                }
            } else {
                ShopifyHelper::log("actualizarInventario: Inventario no existe, creando nuevo registro con stock inicial", [
                    'producto_id' => $productoId,
                    'bodega_id' => $bodegaId,
                    'stock_nuevo' => $cantidad,
                ]);

                $inventario = Inventario::create([
                    'id_producto' => $productoId,
                    'id_bodega' => $bodegaId,
                    'stock' => $cantidad,
                    'stock_minimo' => 0,
                    'stock_maximo' => 0,
                ]);

                $esDesdeShopify = !empty($opciones['origen']) && $opciones['origen'] === 'shopify';
                if ($esDesdeShopify && $cantidad != 0) {
                    $producto = Producto::find($productoId);
                    if ($producto) {
                        $inventario->kardex($producto, $cantidad, $producto->precio, $producto->costo, null, [
                            'origen' => 'shopify',
                            'id_usuario' => $usuarioId,
                        ]);
                    }
                }

                ShopifyHelper::log("actualizarInventario: Inventario creado exitosamente", [
                    'inventario_id' => $inventario->id,
                    'producto_id' => $productoId,
                    'bodega_id' => $bodegaId,
                    'stock' => $cantidad,
                ]);
            }

            return [
                'id_producto' => $productoId,
                'id_bodega' => $bodegaId,
                'stock' => ['decrement' => $cantidad],
                'updated_at' => now()
            ];
        } catch (\Exception $e) {
            Log::error('Error en actualizarInventario', [
                'producto_id' => $productoId,
                'bodega_id' => $bodegaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            // Siempre liberar la señal de cache al terminar, sea éxito o excepción.
            if ($esDesdeShopify) {
                Cache::forget("shopify_syncing_inv_{$productoId}");
            }
        }
    }

    /**
     * Procesa el webhook de pedido cancelado de Shopify
     */
    public function procesarVentaCancelada($tokenEmpresa, Request $request)
    {
        // ShopifyHelper::log("orders/cancelled: Webhook de pedido cancelado recibido", [
            // 'shopify_order_id' => $request->id,
            // 'token_empresa' => substr($tokenEmpresa, 0, 8) . '...',
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        try {
            // Buscar la venta por el ID del pedido de Shopify
            $shopifyOrderId = $request->id;
            $referencia = 'SHOPIFY-' . $shopifyOrderId;
            
            $venta = Venta::where('referencia_shopify', $referencia)
                ->where('id_empresa', $empresa->id)
                ->orderBy('cotizacion', 'asc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$venta) {
                Log::warning("Venta no encontrada para el pedido cancelado de Shopify", [
                    'shopify_order_id' => $shopifyOrderId,
                    'referencia_buscada' => $referencia,
                    'empresa_id' => $empresa->id
                ]);
                return response()->json([
                    'status' => 'warning',
                    'mensaje' => 'Venta no encontrada para el pedido cancelado'
                ], 404);
            }

            // Verificar si la venta ya está anulada
            if ($venta->estado === 'Anulada') {
                Log::info("Venta ya está anulada", [
                    'venta_id' => $venta->id,
                    'shopify_order_id' => $shopifyOrderId
                ]);
                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Venta ya estaba anulada'
                ], 200);
            }

            // Si la venta ya fue emitida (DTE enviado a Hacienda), no se modifica desde Shopify.
            if ($this->ventaEmitida($venta)) {
                // ShopifyHelper::log('Cancelación ignorada - venta ya emitida en SmartPyme', [
                    // 'venta_id' => $venta->id,
                    // 'shopify_order_id' => $shopifyOrderId,
                    // 'sello_mh' => $venta->sello_mh,
                // ]);

                return response()->json([
                    'status' => 'ignored',
                    'mensaje' => 'Venta ya emitida en SmartPyme - no se modifica desde Shopify',
                    'venta_id' => $venta->id,
                    'emitida' => true
                ], 200);
            }

            DB::beginTransaction();

            // Marcar la venta como anulada
            $venta->update([
                'estado' => 'Anulada',
                'observaciones' => ($venta->observaciones ? $venta->observaciones . ' | ' : '') . 
                    'Pedido cancelado en Shopify el ' . now()->format('d/m/Y H:i:s')
            ]);

            // Verificar si se debe revertir el inventario según la configuración de Shopify
            $debeRevertirInventario = $this->shopifyVentaService->debeRevertirInventario($request);
            
            // ShopifyHelper::log("orders/cancelled: Decisión de revertir inventario", [
                // 'debe_revertir' => $debeRevertirInventario,
                // 'shopify_order_id' => $shopifyOrderId
            // ]);

            // Solo restaurar el stock si Shopify indica que se debe revertir el inventario
            if ($debeRevertirInventario) {
                foreach ($venta->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto) {
                        $inventario = Inventario::where('id_producto', $producto->id)
                            ->where('id_bodega', $venta->id_bodega)
                            ->first();

                        if ($inventario) {
                            $stockAntes = $inventario->stock;
                            // Incrementar el stock
                            $inventario->increment('stock', $detalle->cantidad);
                            $inventario->refresh();
                            
                            // Validar y convertir valores numéricos
                            $cantidad = is_numeric($detalle->cantidad) ? (float)$detalle->cantidad : 0;
                            $precio = is_numeric($detalle->precio) ? (float)$detalle->precio : 0;
                            $costoProducto = is_numeric($producto->costo) ? (float)$producto->costo : 0;
                            
                            // Registrar en el kardex solo si tenemos valores válidos (signo negativo para Venta Anulada)
                            if ($cantidad > 0) {
                                $inventario->kardex($venta, -$cantidad, $precio, $costoProducto, null, ['origen' => 'shopify']);
                            }
                            
                            // ShopifyHelper::log("orders/cancelled: Stock restaurado para producto", [
                                // 'producto_id' => $producto->id,
                                // 'codigo' => $producto->codigo,
                                // 'nombre' => $producto->nombre,
                                // 'bodega_id' => $venta->id_bodega,
                                // 'stock_anterior' => $stockAntes,
                                // 'cantidad_restaurada' => $cantidad,
                                // 'stock_nuevo' => $inventario->stock,
                                // 'shopify_order_id' => $shopifyOrderId,
                            // ]);
                        }
                    }
                }
            } else {
                Log::info("No se restaura el stock - opción 'Revertir inventario' no marcada en Shopify", [
                    'shopify_order_id' => $shopifyOrderId
                ]);
            }

            DB::commit();

            // ShopifyHelper::log("orders/cancelled: Venta anulada exitosamente desde Shopify", [
                // 'venta_id' => $venta->id,
                // 'shopify_order_id' => $shopifyOrderId,
                // 'estado_anterior' => $venta->getOriginal('estado'),
                // 'debe_revertir_inventario' => $debeRevertirInventario,
            // ]);

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta anulada correctamente',
                'venta_id' => $venta->id,
                'estado' => $venta->estado
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error procesando cancelación de venta desde Shopify: ' . $e->getMessage(), [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la cancelación de la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determina si una venta ya fue emitida (DTE enviado a Hacienda) y por tanto
     * no debe ser modificada por los webhooks de Shopify.
     *
     * @param Venta $venta
     * @return bool
     */
    private function ventaEmitida(Venta $venta)
    {
        return !empty($venta->sello_mh);
    }

    /**
     * Indica si el cliente tiene datos fiscales para emitir un Comprobante de Crédito Fiscal
     * (NCR presente, o NIT con tipo de documento 36).
     *
     * @param Cliente $cliente
     * @return bool
     */
    private function esClienteCreditoFiscal(Cliente $cliente)
    {
        if (!empty($cliente->ncr)) {
            return true;
        }

        if (!empty($cliente->nit) && $cliente->tipo_documento === '36') {
            return true;
        }

        return false;
    }

    /**
     * Indica si el cliente es extranjero (país distinto de El Salvador) y por tanto
     * corresponde emitir una Factura de Exportación.
     *
     * @param Cliente $cliente
     * @return bool
     */
    private function esClienteExtranjero(Cliente $cliente)
    {
        return !empty($cliente->cod_pais) && strtoupper((string) $cliente->cod_pais) !== 'SV';
    }

    /**
     * Resuelve el nombre del documento de facturación según los datos fiscales del cliente:
     * Factura de Exportación (cliente extranjero), Comprobante de Crédito Fiscal o
     * Factura de Consumidor Final.
     *
     * @param Cliente $cliente
     * @return string
     */
    private function resolverNombreDocumentoFiscal(Cliente $cliente)
    {
        if ($this->esClienteExtranjero($cliente)) {
            return 'Factura de exportación';
        }

        if ($this->esClienteCreditoFiscal($cliente)) {
            return 'Crédito fiscal';
        }

        return 'Factura';
    }

    /**
     * Resuelve el documento de facturación para una venta proveniente de Shopify:
     * Factura de Exportación, Comprobante de Crédito Fiscal o Factura de Consumidor
     * Final según los datos fiscales del cliente.
     *
     * @param User $usuario
     * @param Empresa $empresa
     * @param Cliente $cliente
     * @return Documento|null
     */
    private function nombresDocumentoCandidatos(Cliente $cliente): array
    {
        $fiscal = $this->resolverNombreDocumentoFiscal($cliente);
        $preferido = trim((string) ($cliente->tipo_factura_preferida ?? ''));

        if ($preferido === '' || $preferido === $fiscal) {
            return [$fiscal];
        }

        return [$preferido, $fiscal];
    }

    private function buscarDocumentoActivo($usuario, $empresa, string $nombreDocumento, $idSucursal = null)
    {
        $sucursalId = $idSucursal ?: $usuario->id_sucursal;
        $documento = Documento::where('id_sucursal', $sucursalId)
            ->where('nombre', $nombreDocumento)
            ->where('activo', true)
            ->first();

        if (!$documento) {
            $documento = Documento::where('id_empresa', $empresa->id)
                ->where('nombre', $nombreDocumento)
                ->where('activo', true)
                ->first();
        }

        return $documento;
    }

    private function resolverDocumentoFactura($usuario, $empresa, Cliente $cliente, $idSucursal = null)
    {
        $documento = null;

        foreach ($this->nombresDocumentoCandidatos($cliente) as $nombreDocumento) {
            $documento = $this->buscarDocumentoActivo($usuario, $empresa, $nombreDocumento, $idSucursal);
            if ($documento) {
                return $documento;
            }
        }

        // Si no hay documento de Crédito fiscal configurado, usar Factura como respaldo.
        $fiscal = $this->resolverNombreDocumentoFiscal($cliente);
        if ($fiscal === 'Crédito fiscal') {
            $documento = $this->buscarDocumentoActivo($usuario, $empresa, 'Factura', $idSucursal);
        }

        return $documento;
    }

    /**
     * Convierte una cotización (generada desde un pedido pendiente de Shopify) en una venta
     * facturable cuando el pedido pasa a estado pagado. Asigna el documento correspondiente
     * (FCF o CCF), el correlativo y descuenta el inventario. NO emite el DTE (emisión manual).
     *
     * @param Venta $venta
     * @param Empresa $empresa
     * @param User $usuario
     * @return bool
     */
    private function convertirCotizacionAVenta(Venta $venta, Empresa $empresa, $usuario, array $shopifyData = [])
    {
        if ($venta->estado === 'Facturada' || (int) $venta->cotizacion === 0) {
            ShopifyHelper::log("convertirCotizacionAVenta: Omitido - cotización #{$venta->id} ya está {$venta->estado} (cotizacion={$venta->cotizacion})");
            return false;
        }

        $cliente = $venta->cliente;
        if (!$cliente) {
            $cliente = $this->shopifyClienteService->obtenerClienteConsumidorFinal($empresa->id);
        }

        $idSucursalVenta = $venta->id_sucursal ?: $usuario->id_sucursal;
        $documento = $this->resolverDocumentoFactura($usuario, $empresa, $cliente, $idSucursalVenta);
        if (!$documento) {
            Log::channel('shopify')->error('No se encontró documento para convertir cotización en venta', [
                'venta_id' => $venta->id,
                'id_sucursal' => $idSucursalVenta,
                'empresa_id' => $empresa->id,
            ]);
            return false;
        }

        DB::beginTransaction();

        try {
            // Bloquear el documento para asignar correlativo sin condiciones de carrera.
            $documento = Documento::where('id', $documento->id)->lockForUpdate()->first();

            $fechasPago = $this->transformer->fechasOficialesDesdePago();
            $formaPago = !empty($shopifyData)
                ? $this->transformer->mapearFormaPago($shopifyData)
                : $venta->forma_pago;

            $venta->update([
                'cotizacion' => 0,
                'id_documento' => $documento->id,
                'correlativo' => $documento->correlativo,
                'estado' => 'Pagada',
                'fecha' => $fechasPago['fecha'],
                'fecha_pago' => $fechasPago['fecha_pago'],
                'forma_pago' => $formaPago,
                'observaciones_shopify' => ($venta->observaciones_shopify ? $venta->observaciones_shopify . ' | ' : '') .
                    'Pedido pagado en Shopify - cotización convertida a venta el ' . $fechasPago['created_at']->format('d/m/Y H:i:s'),
            ]);
            // horEmi del DTE lee created_at; no es fillable.
            $venta->created_at = $fechasPago['created_at'];
            $venta->save();

            $documento->increment('correlativo');

            // Descontar inventario (las cotizaciones no lo descuentan al crearse).
            $detallesProducto = $venta->detalles()
                ->whereHas('producto', function ($query) {
                    $query->where('tipo', '!=', 'Servicio');
                })
                ->get();

            ShopifyHelper::log("convertirCotizacionAVenta: Descontando inventario para cotización convertida a venta", [
                'venta_id' => $venta->id,
                'documento' => $documento->nombre,
                'correlativo' => $documento->correlativo,
                'id_bodega' => $venta->id_bodega,
                'detalles_count' => count($detallesProducto),
            ]);

            foreach ($detallesProducto as $detalle) {
                $producto = $detalle->producto;
                if (!$producto) {
                    continue;
                }

                $stockAntes = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->value('stock') ?? 0;

                Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->decrement('stock', $detalle->cantidad);

                $inventario = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();

                ShopifyHelper::log("convertirCotizacionAVenta: [INVENTARIO DESCONTADO]", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'bodega_id' => $venta->id_bodega,
                    'stock_anterior' => $stockAntes,
                    'cantidad_descontada' => $detalle->cantidad,
                    'stock_nuevo' => $inventario ? $inventario->stock : null,
                ]);

                if ($inventario) {
                    $inventario->kardex($venta, $detalle->cantidad, $detalle->precio, null, null, ['origen' => 'shopify']);
                }
            }

            // Agregar detalles de envío si el webhook los trae y la venta aún no los tiene.
            // Las cotizaciones crean el detalle de envío al guardarse, pero puede que no existieran
            // (precio 0 en create, luego con precio en updated) o que se perdieran por algún motivo.
            if (!empty($shopifyData['shipping_lines'])) {
                $tieneEnvios = $venta->detalles()
                    ->whereHas('producto', fn($q) =>
                        $q->where('tipo', 'Servicio')
                          ->whereHas('categoria', fn($q2) => $q2->where('nombre', 'envios'))
                    )->exists();

                if (!$tieneEnvios) {
                    $this->shippingService->procesarTiposEnvio(
                        $shopifyData['shipping_lines'],
                        $venta->id,
                        $empresa->id,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                }
            }

            DB::commit();

            Log::channel('shopify')->info('Cotización convertida a venta desde Shopify', [
                'venta_id' => $venta->id,
                'documento' => $documento->nombre,
                'correlativo' => $venta->correlativo,
                'referencia_shopify' => $venta->referencia_shopify,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('shopify')->error('Error al convertir cotización en venta desde Shopify', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }


    /**
     * Actualiza las cantidades de productos en una venta existente
     * 
     * @param Venta $venta
     * @param Request $request
     * @return void
     * @deprecated Usar ShopifyVentaService::actualizarCantidadesProductos() en su lugar
     */
    private function actualizarCantidadesProductos($venta, $request, $usuario)
    {
        Log::info("Iniciando actualización de cantidades de productos", [
            'venta_id' => $venta->id,
            'shopify_order_id' => $request->id,
            'line_items_count' => count($request->line_items ?? [])
        ]);

        $lineItems = $request->line_items ?? [];

        // Guard de seguridad: si no vienen line_items en el webhook, no tocar detalles ni inventario
        if (empty($lineItems) || !is_array($lineItems)) {
            return;
        }

        $financialStatus = $request->financial_status ?? 'pending';
        $esReembolso = $financialStatus === 'refunded';
        $esEdicion = !empty($request->order_edit);

        // ShopifyHelper::log("actualizarCantidadesProductos: Iniciando", [
            // 'venta_id' => $venta->id,
            // 'shopify_order_id' => $request->id,
            // 'line_items_count' => count($lineItems),
            // 'financial_status' => $financialStatus,
            // 'es_reembolso' => $esReembolso,
            // 'es_edicion' => $esEdicion,
        // ]);

        // Helper para resolver la cantidad efectiva de un line_item de Shopify.
        // current_quantity = cantidad vigente en la orden (post-ediciones y reembolsos).
        // quantity         = cantidad original al crear la orden (no cambia).
        //
        // Bug fix: el closure anterior devolvía `quantity` (original) cuando current_quantity era 0
        // y el webhook no traía `order_edit`. Eso ocurre cuando el admin de Shopify edita la orden
        // sin usar la Order Editing API — el producto removido llega con current_quantity=0 pero
        // quantity=1, y al devolver 1 el loop de eliminación nunca lo borraba de la venta.
        // Solución: si Shopify incluye current_quantity, siempre es autoritativo.
        $resolverCantidadShopify = function($item) {
            if (isset($item['current_quantity']) && is_numeric($item['current_quantity'])) {
                return (float)$item['current_quantity'];
            }
            return (float)($item['quantity'] ?? 0);
        };

        // Obtener detalles existentes que corresponden a productos (excluyendo envíos / servicios)
        $detallesExistentes = $venta->detalles()
            ->whereNotNull('id_producto')
            ->whereHas('producto', function($query) {
                $query->where('tipo', '!=', 'Servicio')
                    ->whereDoesntHave('categoria', function($q) {
                        $q->where('nombre', 'envios');
                    });
            })
            ->with('producto')
            ->get();

        // Eliminar detalles que fueron removidos de Shopify (si hubo edición del pedido y ya no están)
        // Nota: En reembolsos no se eliminan detalles para mantener evidencia y registro histórico.
        if (!$esReembolso) {
            foreach ($detallesExistentes as $detalle) {
                $producto = $detalle->producto;
                if (!$producto) {
                    continue;
                }

                // Buscar si el producto del detalle sigue presente en los line_items de Shopify
                // Emparejar por shopify_variant_id, SKU (codigo) o id_producto
                $itemEncontrado = null;
                foreach ($lineItems as $item) {
                    if (!empty($item['variant_id']) && !empty($producto->shopify_variant_id) && $item['variant_id'] == $producto->shopify_variant_id) {
                        $itemEncontrado = $item;
                        break;
                    }
                    if (!empty($item['sku']) && !empty($producto->codigo) && $item['sku'] === $producto->codigo) {
                        $itemEncontrado = $item;
                        break;
                    }
                }

                $cantidadShopify = $itemEncontrado ? $resolverCantidadShopify($itemEncontrado) : 0;

                // Solo eliminar si el producto ya no existe en el pedido o su cantidad quedó en 0 tras edición
                if (!$itemEncontrado || $cantidadShopify == 0) {
                    if ($venta->cotizacion != 1) {
                        $inventario = Inventario::where('id_producto', $producto->id)
                            ->where('id_bodega', $venta->id_bodega)
                            ->first();

                        if ($inventario) {
                            $stockAntes = $inventario->stock;
                            // Incrementar stock porque se está eliminando el producto de la venta
                            $inventario->increment('stock', $detalle->cantidad);
                            $inventario->refresh();

                            // ShopifyHelper::log("orders/updated: Item eliminado de orden Shopify, stock restaurado", [
                                // 'producto_id' => $producto->id,
                                // 'codigo' => $producto->codigo,
                                // 'nombre' => $producto->nombre,
                                // 'bodega_id' => $venta->id_bodega,
                                // 'stock_anterior' => $stockAntes,
                                // 'cantidad_restaurada' => $detalle->cantidad,
                                // 'stock_nuevo' => $inventario->stock,
                                // 'shopify_order_id' => $request->id ?? $venta->referencia_shopify,
                            // ]);

                            // Registrar en el kardex con signo negativo para indicar Venta Anulada (Entrada)
                            $inventario->kardex($venta, -$detalle->cantidad, $detalle->precio, $producto->costo, null, ['origen' => 'shopify']);
                        }
                    }

                    $detalle->delete();
                }
            }
        }

        // Procesar los line_items de Shopify para actualizar cantidades o agregar nuevos
        foreach ($lineItems as $item) {
            // Validar que el item tenga los datos mínimos necesarios
            if (empty($item) || !is_array($item)) {
                // ShopifyHelper::log("Line item inválido o vacío", ['item' => $item], 'warning');
                continue;
            }

            $currentQuantity = $resolverCantidadShopify($item);
            if ($currentQuantity == 0 && !$esReembolso) {
                continue;
            }

            // Buscar el producto por variant_id o SKU
            // Si hay múltiples productos con el mismo variant_id, usar el más reciente
            $producto = null;

            if (!empty($item['variant_id'])) {
                $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                    ->where('id_empresa', $venta->id_empresa)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$producto && !empty($item['sku'])) {
                $producto = Producto::where('codigo', $item['sku'])
                    ->where('id_empresa', $venta->id_empresa)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            // Si no se encuentra el producto, crearlo
            if (!$producto) {
                $productoData = $this->transformer->transformarProducto(
                    $item,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );
                $producto = Producto::create($productoData);
            } else {
                // Si el producto existe pero no tiene shopify_variant_id, auto-vincularlo
                if (!empty($item['variant_id']) && $producto->shopify_variant_id != $item['variant_id']) {
                    $existeConVariant = Producto::where('shopify_variant_id', $item['variant_id'])
                        ->where('id_empresa', $venta->id_empresa)
                        ->where('id', '!=', $producto->id)
                        ->exists();
                    if (!$existeConVariant) {
                        $producto->shopify_variant_id = $item['variant_id'];
                        if (!empty($item['product_id']) && empty($producto->shopify_product_id)) {
                            $producto->shopify_product_id = $item['product_id'];
                        }
                        $producto->save();
                    }
                }
            }

            // Buscar el detalle de venta existente por variant_id para evitar duplicados
            $variantId = $item['variant_id'] ?? null;
            $detalle = null;

            if ($variantId) {
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
                $taxesIncluded = $request->taxes_included ?? false;
                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id, $usuario->id_empresa, $taxesIncluded);
                $detalleData['id_producto'] = $producto->id;
                $detalleData['cantidad'] = $currentQuantity;
                $detalle = $venta->detalles()->create($detalleData);

                // Actualizar inventario para el nuevo producto
                if ($venta->cotizacion != 1) {
                    $stockAntes = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->value('stock') ?? 0;

                    Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->decrement('stock', $currentQuantity);

                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();

                    // ShopifyHelper::log("orders/updated: Nuevo item agregado a la orden, stock descontado", [
                        // 'producto_id' => $producto->id,
                        // 'codigo' => $producto->codigo,
                        // 'nombre' => $producto->nombre,
                        // 'bodega_id' => $venta->id_bodega,
                        // 'stock_anterior' => $stockAntes,
                        // 'cantidad_descontada' => $currentQuantity,
                        // 'stock_nuevo' => $inventario ? $inventario->stock : null,
                        // 'shopify_order_id' => $request->id ?? $venta->referencia_shopify,
                    // ]);

                    if ($inventario) {
                        $inventario->kardex($venta, $currentQuantity, $item['price'] ?? 0, null, null, ['origen' => 'shopify']);
                    }
                }

                // Continuar al siguiente item ya que este es nuevo
                continue;
            } else {
                // Si se encontró un detalle pero con un producto diferente (mismo variant_id), actualizar el id_producto
                if ($detalle->id_producto != $producto->id) {
                    $detalle->update(['id_producto' => $producto->id]);
                }
            }

            $cantidadAnterior = (float)$detalle->cantidad;
            $cantidadNueva = $currentQuantity;

            // Solo actualizar si la cantidad ha cambiado O si es un reembolso
            if ($cantidadAnterior != $cantidadNueva || $esReembolso) {
                // Para reembolsos, mantener la cantidad y total originales para evidencia
                if ($esReembolso) {
                    $cantidadFinal = $cantidadAnterior;
                    $precioProducto = $detalle->precio;
                    $totalFinal = $detalle->total;
                    $ivaFinal = $detalle->iva;
                    $gravadaFinal = $detalle->gravada;
                } else {
                    // Actualización normal
                    $cantidadFinal = $cantidadNueva;
                    $precioProducto = $detalle->precio;
                    if ($cantidadNueva == 0 && !empty($item['price'])) {
                        $precioProducto = floatval($item['price']);
                    }
                    $totalFinal = $cantidadFinal * $precioProducto;

                    // Recalcular IVA y gravada para el detalle individual
                    $ivaPorUnidad = round($precioProducto * 0.13, 2);
                    $ivaFinal = round($cantidadFinal * $ivaPorUnidad, 2);
                    $gravadaFinal = round($cantidadFinal * $precioProducto, 2);
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
                            $stockAntes = $inventario->stock;
                            if ($diferenciaStock > 0) {
                                // Se agregaron productos, reducir stock
                                $inventario->decrement('stock', $diferenciaStock);
                            } else {
                                // Se quitaron productos, incrementar stock
                                $inventario->increment('stock', abs($diferenciaStock));
                            }
                            $inventario->refresh();

                            // ShopifyHelper::log("orders/updated: Cantidad de item modificada, stock ajustado", [
                                // 'producto_id' => $producto->id,
                                // 'codigo' => $producto->codigo,
                                // 'nombre' => $producto->nombre,
                                // 'bodega_id' => $venta->id_bodega,
                                // 'cantidad_anterior' => $cantidadAnterior,
                                // 'cantidad_nueva' => $cantidadNueva,
                                // 'diferencia_stock' => $diferenciaStock,
                                // 'stock_anterior' => $stockAntes,
                                // 'stock_nuevo' => $inventario->stock,
                                // 'shopify_order_id' => $request->id ?? $venta->referencia_shopify,
                            // ]);

                            // Registrar en el kardex:
                            // Positivo = Salida (Venta adicional), Negativo = Entrada (Venta Anulada)
                            $inventario->kardex($venta, $diferenciaStock, $detalle->precio, $producto->costo, null, ['origen' => 'shopify']);
                        }
                    }
                } else {
                    Log::info("Reembolso detectado - no se ajusta inventario", [
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad_mantenida' => $cantidadAnterior
                    ]);
                }
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
    private function actualizarEnvio($venta, $request, $usuario)
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
                $tieneIva = !empty($shippingLine['tax_lines']);
                $precioSinIVA = $tieneIva
                    ? $this->impuestosService->calcularPrecioSinImpuesto($precioNuevo, $venta->id_empresa)
                    : $precioNuevo; // exento: precio completo sin desglosar IVA

                $ivaNuevo = $tieneIva ? ($precioNuevo - $precioSinIVA) : 0.0;

                $totalNuevo = $tieneIva ? $precioSinIVA : $precioNuevo;
                $gravadaNueva = $tieneIva ? $precioSinIVA : 0.0;
                $exentaNueva = $tieneIva ? 0.0 : $precioNuevo;

                if (abs($detalleExistente->precio_con_iva - $precioNuevo) > 0.01) {
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
                        'total'          => $totalNuevo,
                        'gravada'        => $gravadaNueva,
                        'exenta'         => $exentaNueva,
                        'iva'            => $ivaNuevo,
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
        $this->shopifyVentaService->recalcularTotalesVenta($venta);
    }

    /**
     * Recalcula los totales de una venta después de actualizar cantidades
     * 
     * @param Venta $venta
     * @return void
     */
    private function recalcularTotalesVenta($venta)
    {
        $subtotal = 0;
        $iva      = 0;
        $gravada  = 0;
        $exenta   = 0;

        foreach ($venta->detalles()->get() as $detalle) {
            $subtotal += round($detalle->cantidad * $detalle->precio, 2);
            $iva      += round($detalle->iva, 2);
            $gravada  += round($detalle->gravada, 2);
            $exenta   += round($detalle->exenta ?? 0, 2);
        }

        $total = round($gravada + $iva + $exenta, 2); // total correcto incluyendo exentos

        $venta->update([
            'sub_total' => round($subtotal, 2),
            'iva'       => round($iva, 2),
            'gravada'   => round($gravada, 2),
            'exenta'    => round($exenta, 2),
            'total'     => $total,
            'monto_pago'=> $total,
        ]);

        Log::info("Totales de venta recalculados", [
            'venta_id' => $venta->id,
            'referencia_shopify' => $venta->referencia_shopify,
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'gravada' => round($gravada, 2),
            'exenta' => round($exenta, 2),
            'total' => $total,
            'es_venta_shopify' => !empty($venta->referencia_shopify)
        ]);
    }

    /**
     * Procesa el webhook de pedido actualizado de Shopify
     * 
     * @param string $tokenEmpresa
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function procesarVentaActualizada($tokenEmpresa, Request $request)
    {
        Log::info("Webhook de pedido actualizado recibido de Shopify", [
            'shopify_order_id' => $request->id,
            'order_id' => $request->order_id ?? 'N/A',
            'order_edit_order_id' => $request->order_edit['order_id'] ?? 'N/A',
            'token_empresa' => $tokenEmpresa,
            'financial_status' => $request->financial_status ?? 'N/A',
            'fulfillment_status' => $request->fulfillment_status ?? 'N/A'
        ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        try {
            // Buscar la venta existente
            $shopifyOrderId = $request->id ?? $request->order_id;
            
            // Para webhook orders/edited, el order_id está en order_edit.order_id
            if (!$shopifyOrderId && isset($request->order_edit['order_id'])) {
                $shopifyOrderId = $request->order_edit['order_id'];
            }
            
            $orderNumber = $request->order_number;
            $referencia = 'SHOPIFY-' . $shopifyOrderId;
            
            $venta = Venta::where('referencia_shopify', $referencia)
                ->where('id_empresa', $empresa->id)
                ->orderBy('cotizacion', 'asc')
                ->orderBy('id', 'desc')
                ->first();

            // Si no se encuentra por ID, buscar por order_number
            if (!$venta && $orderNumber) {
                ShopifyHelper::log("Buscando venta por order_number", [
                    'order_number' => $orderNumber,
                    'empresa_id' => $empresa->id
                ]);
                
                $venta = Venta::where('referencia_shopify', 'SHOPIFY-' . $orderNumber)
                    ->where('id_empresa', $empresa->id)
                    ->orderBy('cotizacion', 'asc')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$venta) {
                Log::warning("Venta no encontrada para actualización", [
                    'shopify_order_id' => $shopifyOrderId,
                    'order_number' => $orderNumber,
                    'referencia_buscada' => $referencia,
                    'empresa_id' => $empresa->id
                ]);
                return response()->json([
                    'status' => 'warning',
                    'mensaje' => 'Venta no encontrada para actualizar'
                ], 404);
            }

            // Si la venta ya fue emitida (DTE enviado a Hacienda), no se modifica desde Shopify.
            if ($this->ventaEmitida($venta)) {
                Log::channel('shopify')->info('Actualización ignorada - venta ya emitida en SmartPyme', [
                    'venta_id' => $venta->id,
                    'shopify_order_id' => $shopifyOrderId,
                    'sello_mh' => $venta->sello_mh,
                ]);

                return response()->json([
                    'status' => 'ignored',
                    'mensaje' => 'Venta ya emitida en SmartPyme - no se modifica desde Shopify',
                    'venta_id' => $venta->id,
                    'emitida' => true
                ], 200);
            }

            // Obtener usuario para procesar la actualización
            $usuario = User::where('id_empresa', $empresa->id)
                ->where('shopify_status', 'connected')
                ->first();

            if (!$usuario) {
                Log::channel('shopify')->warning("Usuario no encontrado para actualizar venta", [
                    'empresa_id' => $empresa->id,
                    'venta_id' => $venta->id
                ]);
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Usuario no encontrado'
                ], 404);
            }

            $financialStatus = $request->financial_status ?? 'pending';
            $esPagada = ($financialStatus === 'paid' || $financialStatus === 'partially_paid');

            // Cuando un pedido pendiente pasa a pagado y el registro sigue siendo una cotización,
            // se convierte a venta facturable (FCF o CCF). La emisión del DTE queda manual.
            // Se ejecuta ANTES del guard de 10 segundos para no perder la conversión cuando el
            // webhook de pago llega inmediatamente después de la creación del pedido.
            $fueConvertida = false;
            if ($esPagada && (int) $venta->cotizacion === 1) {
                // Si la cotización ya fue facturada en SmartPyme, o ya existe una venta activa para esta orden, omitir
                $yaFacturada = ($venta->estado === 'Facturada')
                    || Venta::where('id_empresa', $empresa->id)
                        ->where('cotizacion', 0)
                        ->where(function ($q) use ($venta, $referencia) {
                            $q->where('num_cotizacion', $venta->id)
                              ->orWhere('referencia_shopify', $referencia);
                        })
                        ->exists();

                if ($yaFacturada) {
                    ShopifyHelper::log("orders/updated: Cotización #{$venta->id} ya fue facturada en SmartPyme, omitiendo conversión redundante", [
                        'venta_id' => $venta->id,
                        'estado' => $venta->estado,
                        'referencia_shopify' => $referencia,
                    ]);
                    return response()->json([
                        'status' => 'ignored',
                        'mensaje' => 'La orden ya fue facturada en SmartPyme',
                        'venta_id' => $venta->id,
                    ], 200);
                }

                if ($this->convertirCotizacionAVenta($venta, $empresa, $usuario, $request->all())) {
                    $venta->refresh();
                    $fueConvertida = true;
                }
            }

            // Verificar si la venta se creó hace menos de 10 segundos
            if ($venta->created_at->diffInSeconds(now()) < 10) {
                Log::info("Venta recién creada, ignorando actualización inmediata", [
                    'venta_id' => $venta->id,
                    'created_at' => $venta->created_at,
                    'shopify_order_id' => $request->id,
                    'tiempo_transcurrido' => $venta->created_at->diffInSeconds(now()) . ' segundos'
                ]);
                
                return response()->json([
                    'status' => 'success',
                    'mensaje' => $fueConvertida ? 'Cotización convertida a venta' : 'Actualización ignorada - venta recién creada',
                    'venta_id' => $venta->id
                ], 200);
            }

            // Actualizar estado de la venta si es necesario.
            // Se omite si la cotización acaba de convertirse en venta, porque la conversión ya
            // dejó el estado en 'Pagada' (evita que 'partially_paid' lo revierta a 'Pendiente').
            $nuevoEstado = $this->shopifyVentaService->mapearEstado($financialStatus);

            // Shopify entrega orders/updated fuera de orden. Un payload con financial_status
            // pending/authorized/partially_paid (riesgo, envío, reintento) puede llegar
            // después del paid y bajar la venta a Pendiente. Reembolso y anulación siguen.
            $degradaPago = $venta->estado === 'Pagada' && $nuevoEstado === 'Pendiente';
            if ($degradaPago) {
                ShopifyHelper::log('orders/updated: se conserva Pagada; el financial_status entrante no la degrada', [
                    'venta_id' => $venta->id,
                    'financial_status' => $financialStatus,
                    'shopify_order_id' => $shopifyOrderId,
                ]);

                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Se conserva Pagada',
                    'venta_id' => $venta->id,
                    'estado' => $venta->estado,
                ], 200);
            }

            app(\App\Services\ShopifyVentaConsolidacionService::class)
                ->consolidar($venta, $request->all(), $usuario);
            $venta->refresh();

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta actualizada correctamente',
                'venta_id' => $venta->id,
                'estado' => $venta->estado
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error procesando actualización de venta desde Shopify: ' . $e->getMessage(), [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la actualización de la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa el webhook de draft order creado de Shopify
     * 
     * @param string $tokenEmpresa
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function procesarDraftOrderCreado($tokenEmpresa, Request $request)
    {
        // Log::info("=== PROCESANDO DRAFT ORDER CREADO DESDE SHOPIFY ===", [
        //     'shopify_draft_order_id' => $request->id ?? 'N/A',
        //     'token_empresa' => $tokenEmpresa,
        //     'status' => $request->status ?? 'N/A',
        //     'total_price' => $request->total_price ?? 'N/A'
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            Log::error("Usuario no encontrado", ['empresa_id' => $empresa->id]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        // Verificar que el usuario tenga bodega asignada
        if (!$usuario->id_bodega) {
            Log::error("Usuario sin bodega asignada", [
                'usuario_id' => $usuario->id,
                'usuario_nombre' => $usuario->name,
                'id_empresa' => $usuario->id_empresa
            ]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario sin bodega asignada'
            ], 400);
        }

        // Obtener documento apropiado
        if ($empresa->facturacion_electronica) {
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
                ->where('nombre', 'Factura')
                ->where('activo', true)
                ->first();
        } else {
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
                ->where('nombre', 'Ticket')
                ->where('activo', true)
                ->first();
        }

        if (!$documento) {
            Log::error("Ningún documento encontrado", [
                'id_sucursal' => $usuario->id_sucursal,
                'facturacion_electronica' => $empresa->facturacion_electronica
            ]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Ningún documento activo encontrado para la sucursal'
            ], 500);
        }

        try {
            // Verificar si el draft order ya fue procesado previamente
            $referenciaShopify = 'DRAFT-' . $request->id;
            $ventaExistente = Venta::where('referencia_shopify', $referenciaShopify)
                ->where('id_empresa', $usuario->id_empresa)
                ->first();

            if ($ventaExistente) {
                Log::info("Draft Order duplicado detectado - orden ya procesada previamente", [
                    'shopify_draft_order_id' => $request->id,
                    'venta_id_existente' => $ventaExistente->id,
                    'referencia_shopify' => $referenciaShopify,
                    'webhook_id' => $request->header('X-Shopify-Webhook-Id'),
                    'fecha_creacion_venta' => $ventaExistente->created_at
                ]);

                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Draft Order ya procesado previamente',
                    'venta_id' => $ventaExistente->id,
                    'duplicado' => true
                ], 200);
            }

            // Verificar duplicados por webhook_id usando cache (opcional - si falla Redis/cache, continuamos)
            $webhookId = $request->header('X-Shopify-Webhook-Id');
            if ($webhookId) {
                try {
                    $cacheKey = "shopify_webhook_processed_{$webhookId}";
                    if (Cache::has($cacheKey)) {
                        Log::warning("Webhook duplicado detectado por webhook_id (Draft Order)", [
                            'shopify_draft_order_id' => $request->id,
                            'webhook_id' => $webhookId,
                            'referencia_shopify' => $referenciaShopify
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'mensaje' => 'Webhook ya procesado previamente',
                            'duplicado' => true
                        ], 200);
                    }

                    // Marcar webhook como procesado por 1 hora
                    Cache::put($cacheKey, true, 3600);
                } catch (\Throwable $e) {
                    // Redis/cache no disponible (ej: MISCONF) - continuar sin cache
                    Log::warning("Cache no disponible para verificación de webhook duplicado (Draft Order) - continuando", [
                        'error' => $e->getMessage(),
                        'shopify_draft_order_id' => $request->id,
                    ]);
                }
            }

            DB::beginTransaction();

            // Mapear canal de venta según el tipo de canal de Shopify
            $canalId = $this->mapearCanalVenta($request, $usuario->id_empresa);

            // Preparar datos del request para el transformer
            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_bodega' => $usuario->id_bodega,
                'id_sucursal' => $usuario->id_sucursal,
                'id_documento' => $documento->id,
                'id_canal' => $canalId
            ]);

            // Verificar si hay datos de cliente válidos
            $customer = $request->customer ?? [];
            $hasValidCustomer = !empty($customer) && 
                (!empty($customer['first_name']) || !empty($customer['last_name']) || 
                 !empty($customer['email']) || !empty($customer['phone']));
            
            if ($hasValidCustomer) {
                // Transformar cliente si hay datos válidos
                $clienteData = $this->transformer->transformarCliente($request->all());
                
                // Log::info('=== PROCESANDO CLIENTE EN DRAFT ORDER SHOPIFY ===', [
                //     'shopify_draft_order_id' => $request->id ?? 'N/A',
                //     'shopify_customer_id' => $request->customer['id'] ?? 'N/A',
                //     'customer_email' => $clienteData['correo'],
                //     'customer_name' => $clienteData['nombre'] . ' ' . $clienteData['apellido'],
                //     'empresa_id' => $usuario->id_empresa,
                //     'usuario_id' => $usuario->id
                // ]);
                
                $cliente = $this->shopifyClienteService->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            } else {
                // Usar cliente "Consumidor Final" por defecto
                $cliente = $this->shopifyClienteService->obtenerClienteConsumidorFinal($usuario->id_empresa);
                
                // Log::info('=== USANDO CLIENTE CONSUMIDOR FINAL ===', [
                //     'shopify_draft_order_id' => $request->id ?? 'N/A',
                //     'cliente_id' => $cliente->id,
                //     'cliente_nombre' => $cliente->nombre_completo,
                //     'empresa_id' => $usuario->id_empresa,
                //     'usuario_id' => $usuario->id
                // ]);
            }
            
            // Log::info('=== CLIENTE PROCESADO EN DRAFT ORDER ===', [
            //     'cliente_id' => $cliente->id,
            //     'cliente_correo' => $cliente->correo,
            //     'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
            //     'cliente_creado' => $cliente->wasRecentlyCreated,
            //     'shopify_draft_order_id' => $request->id ?? 'N/A',
            //     'shopify_customer_id' => $request->customer['id'] ?? 'N/A'
            // ]);

            // Transformar venta (draft order se trata como venta pendiente)
            $ventaData = $this->transformer->transformarVenta(
                $request->all(),
                $cliente->id,
                $documento->id,
                $documento->correlativo
            );

            // Marcar como draft order y estado pendiente
            $ventaData['estado'] = 'Pendiente';
            $ventaData['referencia_shopify'] = 'DRAFT-' . $request->id;
            $ventaData['observaciones'] = 'Draft Order creado desde Shopify - ' . now()->format('d/m/Y H:i:s');

            $venta = Venta::create($ventaData);
            
            Log::info("Draft Order creado como venta pendiente", [
                'venta_id' => $venta->id,
                'shopify_draft_order_id' => $request->id,
                'referencia' => $ventaData['referencia_shopify']
            ]);

            // Procesar line items del draft order
            if (!empty($request->line_items)) {
                foreach ($request->line_items as $item) {
                    // Validar que el item tenga los datos mínimos necesarios
                    if (empty($item) || !is_array($item)) {
                        Log::warning("Line item inválido o vacío en draft order", ['item' => $item]);
                        continue;
                    }

                    Log::info("Procesando line item de draft order", [
                        'variant_id' => $item['variant_id'] ?? 'N/A', 
                        'sku' => $item['sku'] ?? 'N/A',
                        'title' => $item['title'] ?? 'N/A'
                    ]);
                    
                    $producto = null;
                    
                    // Buscar producto por variant_id si existe
                    if (!empty($item['variant_id'])) {
                        $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                            ->where('id_empresa', $usuario->id_empresa)
                            ->first();
                    }

                    // Si no se encuentra por variant_id, buscar por SKU
                    if (!$producto && !empty($item['sku'])) {
                        $producto = Producto::where('codigo', $item['sku'])
                            ->where('id_empresa', $usuario->id_empresa)
                            ->first();
                    }

                    // Si no se encuentra el producto, crearlo
                    if (!$producto) {
                        $productoData = $this->transformer->transformarProducto(
                            $item,
                            $usuario->id_empresa,
                            $usuario->id,
                            $usuario->id_sucursal
                        );
                        $producto = Producto::create($productoData);
                    } else {
                        // Si el producto ya existía pero no tiene vinculado shopify_variant_id, auto-vincularlo
                        if (!empty($item['variant_id']) && $producto->shopify_variant_id != $item['variant_id']) {
                            $existeConVariant = Producto::where('shopify_variant_id', $item['variant_id'])
                                ->where('id_empresa', $usuario->id_empresa)
                                ->where('id', '!=', $producto->id)
                                ->exists();
                            if (!$existeConVariant) {
                                $producto->shopify_variant_id = $item['variant_id'];
                                if (!empty($item['product_id']) && empty($producto->shopify_product_id)) {
                                    $producto->shopify_product_id = $item['product_id'];
                                }
                                $producto->save();
                            }
                        }
                    }

                    // Crear detalle de venta
                    $taxesIncluded = $request->taxes_included ?? false;
                    $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id, $usuario->id_empresa, $taxesIncluded);
                    $detalleData['id_producto'] = $producto->id;
                    $venta->detalles()->create($detalleData);

                    Log::info("Detalle de draft order creado", [
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad' => $item['quantity'],
                        'precio' => $item['price']
                    ]);
                }
            }

            // Procesar tipos de envío si existen
            // Los draft orders usan shipping_line (singular) en lugar de shipping_lines (plural)
            $shippingLines = $request->shipping_lines ?? [];
            if (empty($shippingLines) && !empty($request->shipping_line)) {
                $shippingLines = [$request->shipping_line];
            }
            
            if (!empty($shippingLines)) {
                Log::info("Procesando tipos de envío en draft order", [
                    'venta_id' => $venta->id,
                    'shipping_lines_count' => count($shippingLines),
                    'shipping_line_singular' => !empty($request->shipping_line),
                    'shipping_lines_plural' => !empty($request->shipping_lines)
                ]);

                $detallesEnvio = $this->shippingService->procesarTiposEnvio(
                    $shippingLines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );

                Log::info("Detalles de envío procesados en draft order", [
                    'venta_id' => $venta->id,
                    'detalles_creados' => count($detallesEnvio)
                ]);
            }

            // Guardar impuesto de la venta en venta_impuestos
            // Comentado: El IVA ya se guarda directamente en la venta
            // if ($venta->iva > 0) {
            //     $this->impuestosService->guardarImpuestoVenta(
            //         $venta->id,
            //         $venta->iva,
            //         $usuario->id_empresa
            //     );

            //     Log::info("Impuesto de draft order guardado", [
            //         'venta_id' => $venta->id,
            //         'monto_impuesto' => $venta->iva,
            //         'empresa_id' => $usuario->id_empresa
            //     ]);
            // }

            // Incrementar correlativo del documento
            $documento = Documento::findOrfail($venta->id_documento);
            $documento->increment('correlativo');

            DB::commit();

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Draft Order procesado correctamente como venta pendiente',
                'venta_id' => $venta->id,
                'referencia' => $ventaData['referencia_shopify']
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error procesando draft order de Shopify: ' . $e->getMessage(), [
                'shopify_draft_order_id' => $request->id ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar el draft order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa el webhook de prueba enviado por Shopify
     * 
     * @param Request $request
     * @param Empresa $empresa
     * @return \Illuminate\Http\JsonResponse
     */
    private function procesarPruebaWebhook(Request $request, $empresa)
    {
        // Log::info("Webhook de prueba recibido de Shopify", [
        //     'empresa_id' => $empresa->id,
        //     'empresa_nombre' => $empresa->nombre,
        //     'timestamp' => now(),
        //     'headers' => $request->headers->all(),
        //     'payload' => $request->all()
        // ]);

        // Verificar que el webhook de prueba contenga los datos esperados
        $testData = $request->all();
        
        // Shopify envía un payload de prueba con información básica
        $response = [
            'status' => 'success',
            'message' => 'Webhook de prueba procesado correctamente',
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'shopify_status' => $empresa->shopify_status
            ],
            'webhook_info' => [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
                'webhook_id' => $request->header('X-Shopify-Webhook-Id'),
                'timestamp' => now()->toISOString()
            ],
            'test_data_received' => !empty($testData)
        ];

        // Log::info("Respuesta del webhook de prueba", $response);

        return response()->json($response, 200);
    }

    /**
     * Mapea el canal de venta de Shopify al canal correspondiente en el sistema
     * 
     * @param Request $request
     * @param int $empresaId
     * @return int
     */
    private function mapearCanalVenta(Request $request, $empresaId)
    {
        // Obtener el canal de Shopify desde el request
        $shopifyChannel = $request->input('source_name', '');
        
        Log::info('Mapeando canal de venta desde Shopify', [
            'shopify_channel' => $shopifyChannel,
            'empresa_id' => $empresaId,
            'shopify_order_id' => $request->id ?? 'N/A'
        ]);

        // Buscar o crear los canales según el mapeo
        $canalId = null;

        switch ($shopifyChannel) {
            case 'Online Store':
                // Mapear a "Página Web"
                $canalId = $this->buscarOCrearCanal('Página Web', $empresaId);
                break;
                
            case 'Point of sale':
                // Mapear a "Tienda Física"
                $canalId = $this->buscarOCrearCanal('Tienda Física', $empresaId);
                break;
                
            case '':
            case null:
            default:
                // Cuando está vacío o es otro tipo, mapear a "Redes Sociales"
                $canalId = $this->buscarOCrearCanal('Redes Sociales', $empresaId);
                break;
        }

        Log::info('Canal de venta mapeado', [
            'shopify_channel' => $shopifyChannel,
            'canal_id' => $canalId,
            'empresa_id' => $empresaId
        ]);

        return $canalId;
    }

    /**
     * Busca o crea un canal de venta
     * 
     * @param string $nombreCanal
     * @param int $empresaId
     * @return int
     */
    private function buscarOCrearCanal($nombreCanal, $empresaId)
    {
        $canal = \App\Models\Admin\Canal::where('nombre', $nombreCanal)
            ->where('id_empresa', $empresaId)
            ->first();

        if (!$canal) {
            $canal = \App\Models\Admin\Canal::create([
                'nombre' => $nombreCanal,
                'descripcion' => "Canal creado automáticamente desde Shopify - {$nombreCanal}",
                'enable' => true,
                'cobra_propina' => false,
                'envios' => false,
                'id_empresa' => $empresaId
            ]);

            Log::info('Canal de venta creado automáticamente', [
                'canal_id' => $canal->id,
                'nombre' => $nombreCanal,
                'empresa_id' => $empresaId
            ]);
        }

        return $canal->id;
    }

    /**
     * Procesa la creación de una sucursal recibida vía webhook locations/create de Shopify.
     */
    private function procesarUbicacionCreadaShopify(Request $request, $empresa)
    {
        $payload = $request->all();
        $locationService = app(ShopifyLocationService::class);
        $resultado = $locationService->crearSucursalDesdeShopifyPayload($payload, $empresa);

        ShopifyHelper::log("locations/create procesado", [
            'empresa_id' => $empresa->id,
            'location_id' => $request->id,
            'resultado' => $resultado,
        ]);

        return response()->json([
            'status' => ($resultado['success'] ?? false) ? 'success' : 'error',
            'mensaje' => $resultado['mensaje'] ?? '',
        ], ($resultado['success'] ?? false) ? 200 : 400);
    }

    /**
     * Procesa la actualización o cambio de estado de una sucursal recibida vía webhook
     * locations/update, locations/activate o locations/deactivate de Shopify.
     */
    private function procesarUbicacionActualizadaShopify(Request $request, $empresa, $topic)
    {
        $payload = $request->all();
        $locationService = app(ShopifyLocationService::class);
        $resultado = $locationService->actualizarSucursalDesdeShopifyPayload($payload, $empresa, $topic);

        ShopifyHelper::log("{$topic} procesado", [
            'empresa_id' => $empresa->id,
            'location_id' => $request->id,
            'resultado' => $resultado,
        ]);

        return response()->json([
            'status' => ($resultado['success'] ?? false) ? 'success' : 'error',
            'mensaje' => $resultado['mensaje'] ?? '',
        ], ($resultado['success'] ?? false) ? 200 : 400);
    }

    /**
     * Procesa la eliminación de una sucursal recibida vía webhook locations/delete de Shopify.
     */
    private function procesarUbicacionEliminadaShopify(Request $request, $empresa)
    {
        $shopifyLocationId = $request->input('id');
        $locationService = app(ShopifyLocationService::class);
        $resultado = $locationService->eliminarSucursalDesdeShopify($shopifyLocationId, $empresa);

        ShopifyHelper::log("locations/delete procesado", [
            'empresa_id' => $empresa->id,
            'location_id' => $shopifyLocationId,
            'resultado' => $resultado,
        ]);

        return response()->json([
            'status' => ($resultado['success'] ?? false) ? 'success' : 'error',
            'mensaje' => $resultado['mensaje'] ?? '',
            'accion' => $resultado['accion'] ?? '',
        ], ($resultado['success'] ?? false) ? 200 : 400);
    }
}
