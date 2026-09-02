<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Helpers\ShopifyHelper;
use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToShopify;
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
use App\Services\ShopifyImageService;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ShopifySyncCache;
use App\Services\FidelizacionCliente\ConsumoPuntosService;

class ShopifyController extends Controller
{
    protected $transformer;
    protected $cache;
    protected $shippingService;
    protected $impuestosService;
    protected $imageService;


    public function __construct(ShopifyTransformer $transformer, ShopifySyncCache $cache, ShippingService $shippingService, \App\Services\ImpuestosService $impuestosService, ShopifyImageService $imageService)
    {
        $this->transformer = $transformer;
        $this->cache = $cache;
        $this->shippingService = $shippingService;
        $this->impuestosService = $impuestosService;
        $this->imageService = $imageService;
    }

    public function handle($tokenEmpresa, Request $request)
    {
        // Log::info("Webhook Shopify recibido para token: {$tokenEmpresa}");
         //Log::info("Datos del webhook: ", $request->all());

        $webhookTopic = $request->header('X-Shopify-Topic');

        //Log::info("Tipo de webhook: {$webhookTopic}");


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
            Log::error("Usuario no encontrado para empresa", [
                'empresa_id' => $empresa->id,
                'empresa_nombre' => $empresa->nombre,
                'token' => $tokenEmpresa
            ]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        // Log::info("Usuario encontrado para webhook", [
        //     'usuario_id' => $usuario->id,
        //     'usuario_nombre' => $usuario->name,
        //     'id_empresa' => $usuario->id_empresa,
        //     'id_bodega' => $usuario->id_bodega,
        //     'id_sucursal' => $usuario->id_sucursal,
        //     'shopify_status' => $usuario->shopify_status
        // ]);

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

        try {
            switch ($webhookTopic) {
                case 'test':
                     //Log::info("Procesando prueba webhook");
                    return $this->procesarPruebaWebhook($request, $empresa);

                case 'orders/create':
                     //Log::info("Procesando venta creada");
                    return $this->procesarVenta($tokenEmpresa, $request);

                case 'orders/cancelled':
                     //Log::info("Procesando venta cancelada");
                    return $this->procesarVentaCancelada($tokenEmpresa, $request);

                case 'orders/updated':
                     //Log::info("Procesando venta actualizada");
                    return $this->procesarVentaActualizada($tokenEmpresa, $request);

                case 'orders/edited':
                     //Log::info("Procesando venta editada - redirigiendo a orders/updated");
                    // orders/edited no tiene información completa, usar orders/updated
                    return response()->json([
                        'status' => 'success',
                        'mensaje' => 'orders/edited recibido - usar orders/updated para información completa'
                    ], 200);

                case 'customers/create':
                     //Log::info("Procesando cliente creado");
                    return $this->procesarClienteCreado($request, $empresa, $usuario);

                case 'customers/update':
                     //Log::info("Procesando cliente actualizado");
                    return $this->procesarClienteActualizado($request, $empresa, $usuario);

                case 'products/create':
                     //Log::info("Procesando producto creado");
                    return $this->procesarProductoActualizado($request, $empresa, $usuario);

                case 'products/update':
                     //Log::info("Procesando producto actualizado");
                    return $this->procesarProductoActualizado($request, $empresa, $usuario);

                case 'draft_orders/create':
                    // return $this->procesarDraftOrderCreado($tokenEmpresa, $request);
                     //Log::info("Draft order ignorado - solo se procesan órdenes pagadas");
                    return response()->json([
                        'status' => 'ignored',
                        'mensaje' => 'Draft orders no se procesan - solo órdenes pagadas'
                    ], 200);

                case 'inventory_levels/update':
                     //Log::info("Procesando ajuste de inventario desde Shopify");
                    return $this->procesarInventarioActualizadoShopify($request, $empresa, $usuario);

                default:
                     //Log::warning("Tipo de webhook no manejado: {$webhookTopic}");
                    return response()->json(['message' => 'Webhook recibido pero no procesado'], 200);
            }
        } catch (\Exception $e) {
            Log::error("Error procesando webhook Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function procesarProductoActualizado(Request $request, $empresa, $usuario)
    {
        // Log::info("Producto desde Shopify", ['product_id' => $request->id]);

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
            // Log::channel('shopify')->warning("No se pudieron transformar productos válidos desde Shopify", [
            //     'shopify_product_id' => $request->id
            // ]);
            return response()->json(['status' => 'success', 'message' => 'No valid products to process'], 200);
        }

        $categoriaData = $this->transformer->transformarCategoriaDesdeShopify(
            $request->all(),
            $empresa->id
        );
        // Log::info("Categoria desde Shopify", ['categoria_id' => $categoriaData]);

        foreach ($productosData as $productoData) {
            $variantImageId = $productoData['shopify_variant_image_id'] ?? null;
            unset($productoData['shopify_variant_image_id']);

            $producto = $this->buscarProductoExistente($request->id, $productoData, $empresa->id);
            //Log::info("Producto existente", ['producto_id' => $producto->id]);

            // Log::info("Data producto", ['producto_id' => $productoData]);
            $categoria = $this->obtenerCategoria($request->all(), $categoriaData, $empresa->id);
            $productoData['id_categoria'] = $categoria->id;

            if ($producto) {
                if ($this->cache->isShopifyDataDifferent($producto, $productoData)) {
                    $this->cache->lockSync($producto->id);

                    $this->actualizarProductoExistente($producto, $productoData, $usuario);

                    $producto->fresh();
                    $this->cache->saveProductSnapshot($producto);

                    // Log::info("Producto actualizado desde Shopify", ['producto_id' => $producto->id]);
                }

                // Sincronizar imágenes SIEMPRE (independiente de si cambiaron los datos del producto),
                // validando URL y reemplazando solo si la URL o el hash cambiaron.
                $this->procesarImagenes($request, $producto->id, $variantImageId);
            } else {
                // Verificación adicional de duplicados antes de crear
                $duplicadoPorSKU = !empty($productoData['codigo']) ? 
                    Producto::where('codigo', $productoData['codigo'])
                        ->where('id_empresa', $empresa->id)
                        ->exists() : false;

                if ($duplicadoPorSKU) {
                    Log::channel('shopify')->warning("Intento de crear producto duplicado por SKU", [
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

    private function buscarProductoExistente($shopifyId, $productoData, $empresaId)
    {
        // Búsqueda principal por IDs de Shopify
        $producto = Producto::where('shopify_product_id', $shopifyId)
            ->where('shopify_variant_id', $productoData['shopify_variant_id'])
            ->where('id_empresa', $empresaId)
            ->first();

        // Si no se encuentra, buscar por SKU canónico de Shopify como respaldo
        if (!$producto && !empty($productoData['shopify_sku'])) {
            $producto = Producto::where('shopify_sku', $productoData['shopify_sku'])
                ->where('id_empresa', $empresaId)
                ->first();
        }

        // Si no se encuentra, buscar por SKU del proveedor como respaldo (para productos creados antes de la integración)
        if (!$producto && !empty($productoData['codigo'])) {
            $producto = Producto::where('codigo', $productoData['codigo'])
                ->where('id_empresa', $empresaId)
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
        $available = (int) $request->input('available', 0);

        if (empty($inventoryItemId)) {
            Log::channel('shopify')->warning('Webhook inventory_levels/update sin inventory_item_id');
            return response()->json(['status' => 'ignored', 'message' => 'Missing inventory_item_id'], 200);
        }

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('shopify_inventory_item_id', $inventoryItemId)
            ->first();

        if (!$producto) {
            // Log::info('Webhook inventory_levels/update: producto no encontrado por shopify_inventory_item_id', [
            //     'inventory_item_id' => $inventoryItemId,
            // ]);
            return response()->json(['status' => 'ignored', 'message' => 'Product not linked'], 200);
        }

        $inventario = Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->first();

        $stockAnterior = $inventario ? (int) $inventario->stock : 0;

        $this->actualizarInventario(
            $producto->id,
            $available,
            $usuario->id_bodega,
            $usuario->id,
            ['origen' => 'shopify', 'stock_anterior' => $stockAnterior]
        );

        if ($inventario) {
            $this->cache->saveInventorySnapshot($inventario->fresh(), $producto->id);
        }

        // Log::info('Inventario actualizado desde Shopify', [
        //     'producto_id' => $producto->id,
        //     'inventory_item_id' => $inventoryItemId,
        //     'stock_anterior' => $stockAnterior,
        //     'available' => $available,
        // ]);

        return response()->json(['status' => 'success', 'message' => 'Inventario actualizado'], 200);
    }

    private function actualizarProductoExistente($producto, $productoData, $usuario)
    {
        $stockActual = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->value('stock') ?? 0;

        // Extraer datos especiales que no van al modelo
        $stockNuevo = $productoData['_stock'] ?? 0;
        $idUsuario = $productoData['_id_usuario'] ?? $usuario->id;
        $idSucursal = $productoData['_id_sucursal'] ?? $usuario->id_sucursal;
        
        // Limpiar datos especiales del array
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal']);

        // NO marcar syncing_from_shopify para webhooks - solo para importaciones masivas
        $productoData['last_shopify_sync'] = now();

        // SKU de Shopify: se guarda tal cual en codigo y shopify_sku
        $productoData['shopify_sku'] = !empty($productoData['codigo']) ? $productoData['codigo'] : null;

        $producto->update($productoData);

        if ($stockActual != $stockNuevo) {
            $this->actualizarInventario($producto->id, $stockNuevo, $usuario->id_bodega, $idUsuario, [
                'origen' => 'shopify',
                'stock_anterior' => $stockActual,
            ]);

            $inventario = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
                ->where('id_bodega', $usuario->id_bodega)
                ->first();

            if ($inventario) {
                $this->cache->saveInventorySnapshot($inventario, $producto->id);
            }
        }

        // No procesar imágenes durante actualización de productos existentes
        // $this->procesarImagenes(request(), $producto->id);
    }


    private function crearNuevoProducto($productoData, $usuario, $request, $variantImageId = null)
    {
        // Extraer datos especiales que no van al modelo
        $stock = $productoData['_stock'] ?? 0;
        $idUsuario = $productoData['_id_usuario'] ?? $usuario->id;
        $idSucursal = $productoData['_id_sucursal'] ?? $usuario->id_sucursal;
        
        // Limpiar datos especiales del array
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal'], $productoData['shopify_variant_image_id']);
        
        // NO marcar syncing_from_shopify para webhooks - solo para importaciones masivas
        $productoData['last_shopify_sync'] = now();

        // SKU de Shopify: se guarda tal cual en codigo y shopify_sku
        $productoData['shopify_sku'] = !empty($productoData['codigo']) ? $productoData['codigo'] : null;
        
        $producto = Producto::create($productoData);
        
        $this->actualizarInventario($producto->id, $stock, $usuario->id_bodega, $idUsuario);
        $this->procesarImagenes($request, $producto->id, $variantImageId);

        $inventario = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->first();
            
        if ($inventario) {
            // Log::info('Inventario encontrado después de crear producto, guardando snapshot', [
            //     'producto_id' => $producto->id,
            //     'inventario_id' => $inventario->id,
            //     'stock' => $inventario->stock
            // ]);
            $this->cache->saveInventorySnapshot($inventario, $producto->id);
        } else {
            Log::error('Inventario NO encontrado después de crear producto', [
                'producto_id' => $producto->id,
                'bodega_id' => $usuario->id_bodega,
                'stock_solicitado' => $stock
            ]);
        }

        // Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
        
        return $producto;
    }

    public function procesarImagenes($request, $productoId, $variantImageId = null)
    {
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
            
            $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
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
            Log::error("Error procesando cliente creado desde Shopify: " . $e->getMessage());
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
            
            $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
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
            Log::error("Error procesando cliente actualizado desde Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al actualizar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        // Log::info("=== INICIANDO PROCESAMIENTO DE VENTA ===", [
        //    'token_empresa' => $tokenEmpresa,
        //    'shopify_order_id' => $request->id ?? 'N/A'
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::channel('shopify')->error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        // Log::info("Empresa encontrada", ['empresa_id' => $empresa->id, 'empresa_nombre' => $empresa->nombre]);

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            Log::channel('shopify')->error("Usuario no encontrado", ['empresa_id' => $empresa->id]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        // Log::info("Usuario encontrado", ['usuario_id' => $usuario->id, 'usuario_nombre' => $usuario->name]);

        // Verificar primero si debe crearse como cotización
        $financialStatus = $request->financial_status ?? 'pending';
        $esPagada = ($financialStatus === 'paid' || $financialStatus === 'partially_paid');
        $debeCrearCotizacion = $empresa->facturacion_electronica && !$esPagada;

        // Log::info("Verificando si debe crearse como cotización", [
        //     'empresa_id' => $empresa->id,
        //     'facturacion_electronica' => $empresa->facturacion_electronica,
        //     'financial_status' => $financialStatus,
        //     'es_pagada' => $esPagada,
        //     'debe_crear_cotizacion' => $debeCrearCotizacion
        // ]);

        // Log::info("Buscando documento", [
        //     'facturacion_electronica' => $empresa->facturacion_electronica,
        //     'id_sucursal' => $usuario->id_sucursal
        // ]);

        // El documento de facturación se resuelve más adelante, una vez se ha
        // identificado el cliente (FCF o CCF según sus datos fiscales).

        try {
            // Verificar si la orden ya fue procesada previamente
            $referenciaShopify = 'SHOPIFY-' . $request->id;
            $ventaExistente = Venta::where('referencia_shopify', $referenciaShopify)
                ->where('id_empresa', $usuario->id_empresa)
                ->first();

            if ($ventaExistente) {
                // Log::info("Venta duplicada detectada - orden ya procesada previamente", [
                //     'shopify_order_id' => $request->id,
                //     'venta_id_existente' => $ventaExistente->id,
                //     'referencia_shopify' => $referenciaShopify,
                //     'webhook_id' => $request->header('X-Shopify-Webhook-Id'),
                //     'fecha_creacion_venta' => $ventaExistente->created_at
                // ]);

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
                        Log::warning("Webhook duplicado detectado por webhook_id", [
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
                    Log::warning("Cache no disponible para verificación de webhook duplicado - continuando", [
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

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_bodega' => $usuario->id_bodega,
                'id_sucursal' => $usuario->id_sucursal,
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
                
                $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            } else {
                // Usar cliente "Consumidor Final" por defecto
                $cliente = ShopifyHelper::obtenerClienteConsumidorFinal($usuario->id_empresa);
                
                // Log::info('=== USANDO CLIENTE CONSUMIDOR FINAL EN VENTA ===', [
                //     'shopify_order_id' => $request->id ?? 'N/A',
                //     'cliente_id' => $cliente->id,
                //     'cliente_nombre' => $cliente->nombre_completo,
                //     'empresa_id' => $usuario->id_empresa,
                //     'usuario_id' => $usuario->id
                // ]);
            }
            
            // Log::info('=== CLIENTE PROCESADO EN VENTA ===', [
            //     'cliente_id' => $cliente->id,
            //     'cliente_correo' => $cliente->correo,
            //     'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
            //     'cliente_creado' => $cliente->wasRecentlyCreated,
            //     'shopify_order_id' => $request->id ?? 'N/A',
            //     'shopify_customer_id' => $request->customer['id'] ?? 'N/A'
            // ]);

            // Resolver documento de facturación según los datos fiscales del cliente.
            // - Cotizaciones: cualquier documento activo (solo placeholder, no emite).
            // - Facturación electrónica: FCF o CCF según los datos fiscales del cliente.
            // - Sin facturación electrónica: Ticket.
            if ($debeCrearCotizacion) {
                $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
                    ->where('activo', true)
                    ->first();

                if (!$documento) {
                    $documento = Documento::where('id_empresa', $empresa->id)
                        ->where('activo', true)
                        ->first();
                }
            } elseif ($empresa->facturacion_electronica) {
                $documento = $this->resolverDocumentoFactura($usuario, $empresa, $cliente);
            } else {
                $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
                    ->where('nombre', 'Ticket')
                    ->where('activo', true)
                    ->first();
            }

            if (!$documento) {
                DB::rollBack();
                Log::channel('shopify')->error("Ningún documento encontrado", [
                    'id_sucursal' => $usuario->id_sucursal,
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
                // Log::info("Creando cotización en lugar de venta", [
                //     'shopify_order_id' => $request->id,
                //     'financial_status' => $financialStatus
                // ]);
            }
            
            // Log::info("Datos de la venta transformados", $ventaData);
            $venta = Venta::create($ventaData);
            
            // Log::info("Venta creada", ['venta_id' => $venta->id]);

            // Log::info($request->line_items);
            foreach ($request->line_items as $item) {
                // Validar que el item tenga los datos mínimos necesarios
                if (empty($item) || !is_array($item)) {
                    Log::channel('shopify')->warning("Line item inválido o vacío", ['item' => $item]);
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
                    // Log::info("Producto no encontrado, creando nuevo producto", [
                    //     'variant_id' => $item['variant_id'] ?? 'N/A',
                    //     'sku' => $item['sku'] ?? 'N/A',
                    //     'title' => $item['title'] ?? 'N/A'
                    // ]);
                    
                    $productoData = $this->transformer->transformarProducto(
                        $item,
                        $usuario->id_empresa,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                    $producto = Producto::create($productoData);
                    
                    // Log::info("Producto creado", ['producto_id' => $producto->id]);
                }

                $taxesIncluded = $request->taxes_included ?? false;
                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id, $usuario->id_empresa, $taxesIncluded);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

                // Solo actualizar inventario si NO es una cotización
                // Las cotizaciones no afectan el inventario
                if (!$debeCrearCotizacion) {
                    // Actualizar inventario
                    Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->decrement('stock', $item['quantity']);

                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();

                    if ($inventario) {
                        $inventario->kardex($venta, $item['quantity'], $item['price']);
                    }
                }
            }

            // Procesar tipos de envío si existen
            if (!empty($request->shipping_lines)) {
                // Log::info("Procesando tipos de envío", [
                //     'venta_id' => $venta->id,
                //     'shipping_lines_count' => count($request->shipping_lines)
                // ]);

                $detallesEnvio = $this->shippingService->procesarTiposEnvio(
                    $request->shipping_lines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );

                // Log::info("Detalles de envío procesados", [
                //     'venta_id' => $venta->id,
                //     'detalles_creados' => count($detallesEnvio)
                // ]);
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
            }

            // Procesar puntos de fidelización si la venta está pagada
            if ($venta->estado == 'Pagada' && $venta->id_cliente) {
                try {
                    $consumoPuntosService = app(ConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::error('Error al procesar puntos de fidelización en Shopify', [
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
            Log::error('Error procesando venta de Shopify: ' . $e->getMessage());

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
            empty($empresa->shopify_consumer_secret)
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
        $productoParaFlag = null;

        if ($esDesdeShopify) {
            $productoParaFlag = Producto::find($productoId);
            if ($productoParaFlag) {
                $productoParaFlag->syncing_from_shopify = true;
                $productoParaFlag->save();
            }
        }

        try {
            // Log::info('Iniciando actualización de inventario', [
            //     'producto_id' => $productoId,
            //     'cantidad' => $cantidad,
            //     'bodega_id' => $bodegaId,
            //     'usuario_id' => $usuarioId
            // ]);

            $inventario = Inventario::where('id_producto', $productoId)
                ->where('id_bodega', $bodegaId)
                ->first();

            if ($inventario) {
                $stockAnterior = $inventario->stock;
                // Log::info('Inventario existente encontrado, actualizando stock', [
                //     'inventario_id' => $inventario->id,
                //     'stock_anterior' => $stockAnterior,
                //     'stock_nuevo' => $cantidad
                // ]);

                $inventario->update([
                    'stock' => $cantidad
                ]);
                $producto = Producto::find($productoId);

                $esDesdeShopify = !empty($opciones['origen']) && $opciones['origen'] === 'shopify';
                $stockAnteriorOp = $opciones['stock_anterior'] ?? $stockAnterior;

                if ($esDesdeShopify && $producto) {
                    $delta = $cantidad - $stockAnteriorOp;
                    if ($delta != 0) {
                        $inventario->kardex($producto, $delta, $producto->precio, $producto->costo, null, [
                            'origen' => 'shopify',
                            'id_usuario' => $usuarioId,
                        ]);
                    }
                } elseif ($inventario->stock > 0 && $producto) {
                    $producto->id_usuario = $usuarioId;
                    $inventario->kardex($producto, 0, $producto->precio, $producto->costo);
                }
            } else {
                // Log::info('Inventario no existe, creando nuevo registro', [
                //     'producto_id' => $productoId,
                //     'bodega_id' => $bodegaId,
                //     'stock' => $cantidad
                // ]);

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

                // Log::info('Inventario creado exitosamente', [
                //     'inventario_id' => $inventario->id,
                //     'producto_id' => $productoId,
                //     'bodega_id' => $bodegaId,
                //     'stock' => $cantidad
                // ]);
            }

            if ($productoParaFlag) {
                $productoParaFlag->syncing_from_shopify = false;
                $productoParaFlag->save();
            }

            return [
                'id_producto' => $productoId,
                'id_bodega' => $bodegaId,
                'stock' => ['decrement' => $cantidad],
                'updated_at' => now()
            ];
        } catch (\Exception $e) {
            if ($productoParaFlag) {
                $productoParaFlag->syncing_from_shopify = false;
                $productoParaFlag->save();
            }
            Log::channel('shopify')->error('Error en actualizarInventario', [
                'producto_id' => $productoId,
                'bodega_id' => $bodegaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Procesa el webhook de pedido cancelado de Shopify
     */
    public function procesarVentaCancelada($tokenEmpresa, Request $request)
    {
        // Log::info("Webhook de pedido cancelado recibido de Shopify", [
        //     'shopify_order_id' => $request->id,
        //     'token_empresa' => $tokenEmpresa
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::channel('shopify')->error("Token de empresa Shopify no válido: {$tokenEmpresa}");
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
                ->first();

            if (!$venta) {
                // Log::warning("Venta no encontrada para el pedido cancelado de Shopify", [
                //     'shopify_order_id' => $shopifyOrderId,
                //     'referencia_buscada' => $referencia,
                //     'empresa_id' => $empresa->id
                // ]);
                return response()->json([
                    'status' => 'warning',
                    'mensaje' => 'Venta no encontrada para el pedido cancelado'
                ], 404);
            }

            // Verificar si la venta ya está anulada
            if ($venta->estado === 'Anulada') {
                // Log::info("Venta ya está anulada", [
                //     'venta_id' => $venta->id,
                //     'shopify_order_id' => $shopifyOrderId
                // ]);
                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Venta ya estaba anulada'
                ], 200);
            }

            // Si la venta ya fue emitida (DTE enviado a Hacienda), no se modifica desde Shopify.
            if ($this->ventaEmitida($venta)) {
                Log::channel('shopify')->info('Cancelación ignorada - venta ya emitida en SmartPyme', [
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

            DB::beginTransaction();

            // Marcar la venta como anulada
            $venta->update([
                'estado' => 'Anulada',
                'observaciones_shopify' => ($venta->observaciones_shopify ? $venta->observaciones_shopify . ' | ' : '') .
                    'Pedido cancelado en Shopify el ' . now()->format('d/m/Y H:i:s'),
            ]);

            // Verificar si se debe revertir el inventario según la configuración de Shopify
            $debeRevertirInventario = $this->debeRevertirInventario($request);
            
            // Log::info("Decisión de revertir inventario", [
            //     'debe_revertir' => $debeRevertirInventario,
            //     'shopify_order_id' => $shopifyOrderId
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
                            // Incrementar el stock
                            $inventario->increment('stock', $detalle->cantidad);
                            
                            // Validar y convertir valores numéricos
                            $cantidad = is_numeric($detalle->cantidad) ? (float)$detalle->cantidad : 0;
                            $precio = is_numeric($detalle->precio) ? (float)$detalle->precio : 0;
                            $costoProducto = is_numeric($producto->costo) ? (float)$producto->costo : 0;
                            
                            // Registrar en el kardex solo si tenemos valores válidos
                            if ($cantidad > 0) {
                                $inventario->kardex($venta, $cantidad, $precio, $costoProducto);
                            }
                            
                            // Log::info("Stock restaurado para producto", [
                            //     'producto_id' => $producto->id,
                            //     'cantidad_restaurada' => $cantidad,
                            //     'precio' => $precio,
                            //     'costo_usado' => $costoProducto,
                            //     'stock_actual' => $inventario->stock
                            // ]);
                        }
                    }
                }
            }

            DB::commit();

            // Log::info("Venta anulada exitosamente desde Shopify", [
            //     'venta_id' => $venta->id,
            //     'shopify_order_id' => $shopifyOrderId,
            //     'estado_anterior' => $venta->getOriginal('estado')
            // ]);

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta anulada correctamente',
                'venta_id' => $venta->id,
                'estado' => $venta->estado
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Error procesando cancelación de venta desde Shopify: ' . $e->getMessage(), [
            //     'shopify_order_id' => $shopifyOrderId,
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la cancelación de la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determina si se debe revertir el inventario basado en el webhook de Shopify
     */
    private function debeRevertirInventario(Request $request)
    {
        
        // 1. Verificar si hay refunds con restock
        if (isset($request->refunds) && is_array($request->refunds)) {
            foreach ($request->refunds as $refund) {
                if (isset($refund['restock']) && $refund['restock'] === true) {
                    // Log::info("Inventario debe revertirse - refund con restock encontrado", [
                    //     'refund_id' => $refund['id'] ?? 'N/A'
                    // ]);
                    return true;
                }
            }
        }
        
        // 2. Verificar el cancel_reason y financial_status
        $cancelReason = $request->input('cancel_reason');
        $financialStatus = $request->input('financial_status');
        
        // Si el pedido está voided y no hay refunds, generalmente significa que se revierte el inventario
        if ($financialStatus === 'voided' && empty($request->refunds)) {
            // Log::info("Inventario debe revertirse - pedido voided sin refunds", [
            //     'cancel_reason' => $cancelReason,
            //     'financial_status' => $financialStatus
            // ]);
            return true;
        }
        
        // 3. Verificar si hay line_items con información de restock
        if (isset($request->line_items) && is_array($request->line_items)) {
            foreach ($request->line_items as $lineItem) {
                // Si el line item tiene fulfillable_quantity > 0, significa que no se ha enviado
                // y por tanto se debe revertir el inventario
                if (isset($lineItem['fulfillable_quantity']) && $lineItem['fulfillable_quantity'] > 0) {
                    // Log::info("Inventario debe revertirse - line item con fulfillable_quantity > 0", [
                    //     'line_item_id' => $lineItem['id'] ?? 'N/A',
                    //     'fulfillable_quantity' => $lineItem['fulfillable_quantity']
                    // ]);
                    return true;
                }
            }
        }
        
        // 4. Por defecto, si no hay información específica, asumir que NO se debe revertir
        // Esto es más seguro para evitar restaurar stock cuando no se debe
        // Log::info("No se revierte inventario - no se encontró indicación clara de restock", [
        //     'cancel_reason' => $cancelReason,
        //     'financial_status' => $financialStatus,
        //     'has_refunds' => !empty($request->refunds)
        // ]);
        
        return false;
    }

    /**
     * Busca o actualiza un cliente optimizando por shopify_customer_id, correo y teléfono
     * 
     * @param array $clienteData
     * @param int $empresaId
     * @return Cliente
     */
    private function buscarOActualizarCliente($clienteData, $empresaId)
    {
        $shopifyCustomerId = $clienteData['shopify_customer_id'] ?? null;
        $correo = $clienteData['correo'] ?? null;
        $telefono = $clienteData['telefono'] ?? null;
        
        // Si es "Consumidor Final" o no vienen datos identificativos, usar cliente Consumidor Final por defecto
        $nombreCompleto = trim(($clienteData['nombre'] ?? '') . ' ' . ($clienteData['apellido'] ?? ''));
        if (
            strcasecmp($nombreCompleto, 'Consumidor Final') === 0 ||
            strcasecmp($clienteData['nombre'] ?? '', 'Consumidor Final') === 0 ||
            (empty($clienteData['nombre']) && empty($clienteData['apellido']) && empty($correo) && empty($telefono) && empty($shopifyCustomerId))
        ) {
            return ShopifyHelper::obtenerClienteConsumidorFinal($empresaId);
        }

        // Validaciones de seguridad para evitar asignaciones incorrectas
        if (!$this->validarDatosCliente($clienteData)) {
            // Log::warning('Datos de cliente inválidos, creando cliente con datos mínimos', [
            //     'shopify_customer_id' => $shopifyCustomerId,
            //     'correo' => $correo,
            //     'telefono' => $telefono
            // ]);
            
            // Crear cliente con datos mínimos válidos
            return $this->crearClienteMinimo($clienteData, $empresaId);
        }
        
        // 1. Si tenemos shopify_customer_id, buscar primero por ese campo
        if ($shopifyCustomerId) {
            $cliente = Cliente::where('shopify_customer_id', $shopifyCustomerId)
                ->where('id_empresa', $empresaId)
                ->first();
                
            if ($cliente) {
                return $this->actualizarClienteExistenteDesdeShopify($cliente, $clienteData);
            }
        }
        
        // 2. Si no se encontró por shopify_customer_id, buscar por correo
        if ($correo) {
            $cliente = Cliente::where('correo', $correo)
                ->where('id_empresa', $empresaId)
                ->first();
                
            if ($cliente) {
                // Validar que no haya conflicto con shopify_customer_id existente
                if ($cliente->shopify_customer_id && $cliente->shopify_customer_id !== $shopifyCustomerId) {
                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }
                
                return $this->actualizarClienteExistenteDesdeShopify($cliente, $clienteData);
            }
        }
        
        // 3. Si no se encontró por correo, buscar por teléfono (con validación adicional)
        if ($telefono) {
            $cliente = Cliente::where('telefono', $telefono)
                ->where('id_empresa', $empresaId)
                ->first();
                
            if ($cliente) {
                // Validar que no haya conflicto con shopify_customer_id existente
                if ($cliente->shopify_customer_id && $cliente->shopify_customer_id !== $shopifyCustomerId) {
                    Log::warning('Conflicto de shopify_customer_id detectado por teléfono', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $telefono,
                        'shopify_customer_id_existente' => $cliente->shopify_customer_id,
                        'shopify_customer_id_nuevo' => $shopifyCustomerId
                    ]);
                    
                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }
                
                // Validar que el correo coincida si está disponible
                if ($correo && $cliente->correo && $cliente->correo !== $correo) {
                    Log::warning('Conflicto de correo detectado por teléfono', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $telefono,
                        'correo_cliente' => $cliente->correo,
                        'correo_pedido' => $correo
                    ]);
                    
                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }
                
                return $this->actualizarClienteExistenteDesdeShopify($cliente, $clienteData);
            }
        }
        
        // 4. Si no existe, crear nuevo cliente
        return Cliente::create($clienteData);
    }

    /**
     * Actualiza de forma segura un cliente existente desde Shopify sin sobreescribir datos fiscales.
     * Rellena únicamente campos de contacto/dirección si están vacíos en el cliente actual.
     *
     * @param Cliente $cliente
     * @param array $clienteData
     * @return Cliente
     */
    private function actualizarClienteExistenteDesdeShopify(Cliente $cliente, array $clienteData)
    {
        // Si el cliente es "Consumidor Final", NO modificar sus datos para preservar el cliente genérico
        if (
            strcasecmp($cliente->nombre ?? '', 'Consumidor Final') === 0 ||
            strcasecmp($cliente->nombre_empresa ?? '', 'Consumidor Final') === 0
        ) {
            return $cliente;
        }

        $camposActualizar = [];

        // 1. Vincular shopify_customer_id si no lo tenía
        if (!empty($clienteData['shopify_customer_id']) && empty($cliente->shopify_customer_id)) {
            $camposActualizar['shopify_customer_id'] = $clienteData['shopify_customer_id'];
        }

        // 2. Actualizar datos de contacto y perfil de la persona si vienen con valor no vacío
        if (!empty($clienteData['nombre'])) {
            $camposActualizar['nombre'] = $clienteData['nombre'];
        }
        if (isset($clienteData['apellido']) && $clienteData['apellido'] !== '') {
            $camposActualizar['apellido'] = $clienteData['apellido'];
        }
        if (!empty($clienteData['correo'])) {
            $camposActualizar['correo'] = $clienteData['correo'];
        }
        if (!empty($clienteData['telefono'])) {
            $camposActualizar['telefono'] = $clienteData['telefono'];
        }
        if (!empty($clienteData['direccion'])) {
            $camposActualizar['direccion'] = $clienteData['direccion'];
        }

        // 3. Ubicación geográfica: estandarizar y asignar si los códigos o labels MH no están definidos o no están homologados
        if ((empty($cliente->cod_pais) || empty($cliente->pais)) && !empty($clienteData['pais'])) {
            $camposActualizar['pais'] = $clienteData['pais'];
            $camposActualizar['cod_pais'] = $clienteData['cod_pais'] ?? null;
        }
        if ((empty($cliente->cod_departamento) || !is_numeric($cliente->cod_departamento) || empty($cliente->departamento)) && !empty($clienteData['departamento'])) {
            $camposActualizar['departamento'] = $clienteData['departamento'];
            $camposActualizar['cod_departamento'] = $clienteData['cod_departamento'] ?? null;
        }
        if ((empty($cliente->cod_municipio) || !is_numeric($cliente->cod_municipio) || empty($cliente->municipio)) && !empty($clienteData['municipio'])) {
            $camposActualizar['municipio'] = $clienteData['municipio'];
            $camposActualizar['cod_municipio'] = $clienteData['cod_municipio'] ?? null;
        }
        if ((empty($cliente->cod_distrito) || !is_numeric($cliente->cod_distrito) || empty($cliente->distrito)) && !empty($clienteData['distrito'])) {
            $camposActualizar['distrito'] = $clienteData['distrito'];
            $camposActualizar['cod_distrito'] = $clienteData['cod_distrito'] ?? null;
        }

        // 4. Nombre de empresa: solo asignar si el cliente no tenía razón social registrada
        if (empty($cliente->nombre_empresa) && !empty($clienteData['nombre_empresa'])) {
            $camposActualizar['nombre_empresa'] = $clienteData['nombre_empresa'];
        }

        // 5. Tipo: si el cliente ya es 'Empresa' o tiene datos fiscales, conservarlo; si no tenía tipo, asignar
        if (empty($cliente->tipo) && !empty($clienteData['tipo'])) {
            $camposActualizar['tipo'] = $clienteData['tipo'];
        }

        // Ejecutar actualización únicamente si hay campos para actualizar
        if (!empty($camposActualizar)) {
            $cliente->fill($camposActualizar);
            if ($cliente->exists) {
                $cliente->save();
            }
        }

        return $cliente;
    }

    /**
     * Excluye campos protegidos del array de datos del cliente (mantenido como resguardo adicional)
     */
    private function excluirCamposProtegidos($clienteData, $empresaId)
    {
        $camposProtegidos = [
            'tipo',
            'nombre_empresa',
            'ncr',
            'nit',
            'dui',
            'giro',
            'cod_giro',
            'tipo_contribuyente',
            'tipo_fiscal',
            'tipo_persona',
            'tipo_documento',
            'clasificacion',
            'cod_departamento',
            'departamento',
            'municipio',
            'cod_municipio',
            'cod_distrito',
            'distrito',
            'cod_pais',
            'pais',
            'empresa_telefono',
            'empresa_direccion',
            'codigo_cliente',
            'habilita_credito',
            'dias_credito',
            'limite_credito',
            'id_vendedor',
            'id_tipo_cliente',
            'nivel'
        ];
        
        $clienteDataProtegido = $clienteData;
        foreach ($camposProtegidos as $campo) {
            unset($clienteDataProtegido[$campo]);
        }
        
        return $clienteDataProtegido;
    }

    /**
     * Valida los datos del cliente para evitar asignaciones incorrectas
     * 
     * @param array $clienteData
     * @return bool
     */
    private function validarDatosCliente($clienteData)
    {
        $correo = $clienteData['correo'] ?? null;
        $nombre = $clienteData['nombre'] ?? null;
        $apellido = $clienteData['apellido'] ?? null;
        
        // Validar que tenga al menos un nombre
        if (empty($nombre) && empty($apellido)) {
            // Log::warning('Cliente sin nombre válido', [
            //     'nombre' => $nombre,
            //     'apellido' => $apellido,
            //     'correo' => $correo
            // ]);
            return false;
        }
        
        // Validar email si existe
        if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            // Log::warning('Email inválido', [
            //     'correo' => $correo,
            //     'nombre' => $nombre
            // ]);
            return false;
        }
        
        // Validar que no sea un email genérico o de prueba
        if ($correo && $this->esEmailGenerico($correo)) {
            // Log::warning('Email genérico detectado', [
            //     'correo' => $correo,
            //     'nombre' => $nombre
            // ]);
            return false;
        }
        
        return true;
    }

    /**
     * Verifica si un email es genérico o de prueba
     * 
     * @param string $email
     * @return bool
     */
    private function esEmailGenerico($email)
    {
        $emailsGenericos = [
            'test@example.com',
            'test@test.com',
            'admin@shopify.com',
            'noreply@shopify.com',
            'support@shopify.com',
            'info@shopify.com',
            'contact@shopify.com'
        ];
        
        $emailLower = strtolower($email);
        
        // Verificar emails genéricos exactos
        if (in_array($emailLower, $emailsGenericos)) {
            return true;
        }
        
        // Verificar patrones genéricos
        $patronesGenericos = [
            '/^test\d*@/',
            '/^admin\d*@/',
            '/^user\d*@/',
            '/^customer\d*@/',
            '/^shopify\d*@/',
            '/^demo\d*@/'
        ];
        
        foreach ($patronesGenericos as $patron) {
            if (preg_match($patron, $emailLower)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Crea un cliente con datos mínimos válidos
     * 
     * @param array $clienteData
     * @param int $empresaId
     * @return Cliente
     */
    private function crearClienteMinimo($clienteData, $empresaId)
    {
        $rawPhone = $clienteData['telefono'] ?? null;
        $telefono = !empty(trim((string)$rawPhone)) ? trim((string)$rawPhone) : null;
        $rawEmail = $clienteData['correo'] ?? null;
        $correo = !empty(trim((string)$rawEmail)) ? trim((string)$rawEmail) : 'cliente@shopify.com';

        $clienteMinimo = [
            'nombre' => !empty($clienteData['nombre']) ? $clienteData['nombre'] : 'Cliente',
            'apellido' => $clienteData['apellido'] ?? 'Shopify',
            'correo' => $correo,
            'telefono' => $telefono,
            'direccion' => $clienteData['direccion'] ?? null,
            'pais' => $clienteData['pais'] ?? 'El Salvador',
            'cod_pais' => $clienteData['cod_pais'] ?? 'SV',
            'municipio' => $clienteData['municipio'] ?? null,
            'cod_municipio' => $clienteData['cod_municipio'] ?? null,
            'distrito' => $clienteData['distrito'] ?? null,
            'cod_distrito' => $clienteData['cod_distrito'] ?? null,
            'departamento' => $clienteData['departamento'] ?? null,
            'cod_departamento' => $clienteData['cod_departamento'] ?? null,
            'tipo' => 'Persona',
            'enable' => 1,
            'id_empresa' => $empresaId,
            'id_usuario' => $clienteData['id_usuario'] ?? null,
            'shopify_customer_id' => $clienteData['shopify_customer_id'] ?? null,
        ];
        
        return Cliente::create($clienteMinimo);
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

    private function buscarDocumentoActivo($usuario, $empresa, string $nombreDocumento)
    {
        $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
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

    private function resolverDocumentoFactura($usuario, $empresa, Cliente $cliente)
    {
        $documento = null;

        foreach ($this->nombresDocumentoCandidatos($cliente) as $nombreDocumento) {
            $documento = $this->buscarDocumentoActivo($usuario, $empresa, $nombreDocumento);
            if ($documento) {
                return $documento;
            }
        }

        // Si no hay documento de Crédito fiscal configurado, usar Factura como respaldo.
        $fiscal = $this->resolverNombreDocumentoFiscal($cliente);
        if ($fiscal === 'Crédito fiscal') {
            $documento = $this->buscarDocumentoActivo($usuario, $empresa, 'Factura');
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
        $cliente = $venta->cliente;
        if (!$cliente) {
            $cliente = ShopifyHelper::obtenerClienteConsumidorFinal($empresa->id);
        }

        $documento = $this->resolverDocumentoFactura($usuario, $empresa, $cliente);
        if (!$documento) {
            Log::channel('shopify')->error('No se encontró documento para convertir cotización en venta', [
                'venta_id' => $venta->id,
                'id_sucursal' => $usuario->id_sucursal,
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

            foreach ($detallesProducto as $detalle) {
                $producto = $detalle->producto;
                if (!$producto) {
                    continue;
                }

                Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->decrement('stock', $detalle->cantidad);

                $inventario = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();

                if ($inventario) {
                    $inventario->kardex($venta, $detalle->cantidad, $detalle->precio);
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
     * Mapea el estado financiero de Shopify al estado de SmartPyme
     * 
     * @param string $shopifyStatus
     * @return string
     */
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

        return $mapeo[$shopifyStatus] ?? 'Pendiente';
    }

    /**
     * Actualiza las cantidades de productos en una venta existente
     * 
     * @param Venta $venta
     * @param Request $request
     * @return void
     */
    private function actualizarCantidadesProductos($venta, $request, $usuario)
    {
        // Log::info("Iniciando actualización de cantidades de productos", [
        //     'venta_id' => $venta->id,
        //     'shopify_order_id' => $request->id,
        //     'line_items_count' => count($request->line_items ?? [])
        // ]);

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
                // Log::info("Eliminando detalle de producto removido de Shopify", [
                //     'detalle_id' => $detalle->id,
                //     'producto_id' => $producto->id,
                //     'producto_nombre' => $producto->nombre,
                //     'cantidad_anterior' => $detalle->cantidad,
                //     'encontrado_en_shopify' => $encontradoEnShopify,
                //     'current_quantity' => $currentQuantity,
                //     'venta_id' => $venta->id
                // ]);
                
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
                        
                        // Log::info("Inventario ajustado por eliminación de producto", [
                        //     'producto_id' => $producto->id,
                        //     'cantidad_devuelta' => $detalle->cantidad,
                        //     'stock_actual' => $inventario->stock,
                        //     'venta_id' => $venta->id
                        // ]);
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
                // Log::info("Saltando item con cantidad 0 - detalle ya eliminado", [
                //     'variant_id' => $item['variant_id'] ?? 'N/A',
                //     'title' => $item['title'] ?? 'N/A',
                //     'venta_id' => $venta->id
                // ]);
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
                // Log::info("Producto no encontrado, creando nuevo producto durante actualización", [
                //     'variant_id' => $item['variant_id'] ?? 'N/A',
                //     'sku' => $item['sku'] ?? 'N/A',
                //     'title' => $item['title'] ?? 'N/A'
                // ]);
                
                $productoData = $this->transformer->transformarProducto(
                    $item,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );
                $producto = Producto::create($productoData);
                
                // Log::info("Producto creado durante actualización", ['producto_id' => $producto->id]);
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
                // Log::info("Detalle de venta no encontrado - creando nuevo detalle para producto agregado", [
                //     'venta_id' => $venta->id,
                //     'producto_id' => $producto->id,
                //     'producto_nombre' => $producto->nombre,
                //     'variant_id' => $variantId
                // ]);
                
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
                    
                    // Log::info("Inventario actualizado para producto nuevo agregado", [
                    //     'producto_id' => $producto->id,
                    //     'cantidad' => $item['quantity'],
                    //     'venta_id' => $venta->id
                    // ]);
                }
                
                // Continuar al siguiente item ya que este es nuevo
                continue;
            } else {
                // Si se encontró un detalle pero con un producto diferente (mismo variant_id), actualizar el id_producto
                if ($detalle->id_producto != $producto->id) {
                    // Log::info("Detalle encontrado con producto diferente - actualizando id_producto", [
                    //     'detalle_id' => $detalle->id,
                    //     'producto_anterior_id' => $detalle->id_producto,
                    //     'producto_nuevo_id' => $producto->id,
                    //     'variant_id' => $variantId,
                    //     'venta_id' => $venta->id
                    // ]);
                    $detalle->update(['id_producto' => $producto->id]);
                }
            }
            
            $cantidadAnterior = $detalle->cantidad;
            // Usar current_quantity si está disponible, sino quantity
            $cantidadNueva = $item['current_quantity'] ?? $item['quantity'];
            $financialStatus = $request->financial_status ?? 'pending';
            $esReembolso = $financialStatus === 'refunded';
            
            // Log::info("Comparando cantidades de producto", [
            //     'venta_id' => $venta->id,
            //     'producto_id' => $producto->id,
            //     'cantidad_anterior' => $cantidadAnterior,
            //     'cantidad_nueva' => $cantidadNueva,
            //     'quantity_shopify' => $item['quantity'],
            //     'current_quantity_shopify' => $item['current_quantity'] ?? 'N/A',
            //     'fulfillable_quantity_shopify' => $item['fulfillable_quantity'] ?? 'N/A',
            //     'diferencia' => $cantidadNueva - $cantidadAnterior,
            //     'financial_status' => $financialStatus,
            //     'es_reembolso' => $esReembolso
            // ]);
            
            // Solo actualizar si la cantidad ha cambiado O si es un reembolso
            if ($cantidadAnterior != $cantidadNueva || $esReembolso) {
                // Log::info("Actualizando cantidad de producto", [
                //     'venta_id' => $venta->id,
                //     'producto_id' => $producto->id,
                //     'cantidad_anterior' => $cantidadAnterior,
                //     'cantidad_nueva' => $cantidadNueva,
                //     'diferencia' => $cantidadNueva - $cantidadAnterior,
                //     'precio_original_detalle' => $detalle->precio,
                //     'precio_shopify' => $item['price'] ?? 'N/A',
                //     'es_reembolso' => $esReembolso,
                //     'financial_status' => $financialStatus
                // ]);
                
                // Para reembolsos, mantener la cantidad y total originales para evidencia
                if ($esReembolso) {
                    // Mantener cantidad y total originales para evidencia
                    $cantidadFinal = $cantidadAnterior; // Mantener cantidad original
                    $precioProducto = $detalle->precio; // Mantener precio original
                    $totalFinal = $detalle->total; // Mantener total original para evidencia
                    $ivaFinal = $detalle->iva; // Mantener IVA original
                    $gravadaFinal = $detalle->gravada; // Mantener gravada original
                    
                    // Log::info("Procesando reembolso - manteniendo valores originales", [
                    //     'venta_id' => $venta->id,
                    //     'producto_id' => $producto->id,
                    //     'cantidad_original' => $cantidadAnterior,
                    //     'cantidad_mantenida' => $cantidadFinal,
                    //     'precio_mantenido' => $precioProducto,
                    //     'total_original' => $detalle->total,
                    //     'total_mantenido' => $totalFinal
                    // ]);
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
                            
                            // Log::info("Inventario ajustado por cambio de cantidad", [
                            //     'producto_id' => $producto->id,
                            //     'diferencia_stock' => $diferenciaStock,
                            //     'stock_actual' => $inventario->stock
                            // ]);
                        }
                    }
                }
            } else {
                // Log::info("Cantidad sin cambios para producto", [
                //     'venta_id' => $venta->id,
                //     'producto_id' => $producto->id,
                //     'cantidad' => $cantidadAnterior
                // ]);
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
        // Log::info("Iniciando actualización de envíos", [
        //     'venta_id' => $venta->id,
        //     'shopify_order_id' => $request->id,
        //     'shipping_lines_count' => count($request->shipping_lines ?? [])
        // ]);

        // Obtener shipping_lines del request
        $shippingLines = $request->shipping_lines ?? [];
        
        if (empty($shippingLines)) {
            // Log::info("No hay shipping_lines para actualizar", [
            //     'venta_id' => $venta->id
            // ]);
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
                // Log::info("Eliminando detalle de envío removido", [
                //     'detalle_id' => $detalleEnvio->id,
                //     'titulo_envio' => $tituloEnvio,
                //     'venta_id' => $venta->id
                // ]);
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
                    // Log::info("Actualizando precio de envío existente", [
                    //     'detalle_id' => $detalleExistente->id,
                    //     'titulo_envio' => $title,
                    //     'precio_anterior' => $detalleExistente->precio_sin_iva,
                    //     'precio_nuevo' => $precioSinIVA,
                    //     'venta_id' => $venta->id
                    // ]);

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
                    // Log::info("Nuevo detalle de envío creado durante actualización", [
                    //     'detalle_id' => $detallesEnvio[0]->id,
                    //     'titulo_envio' => $title,
                    //     'venta_id' => $venta->id
                    // ]);
                }
            }
        }

        // Log::info("Actualización de envíos completada", [
        //     'venta_id' => $venta->id,
        //     'envios_procesados' => count($enviosProcesados),
        //     'envios_eliminados' => count($detallesEnvioExistentes) - count($enviosProcesados)
        // ]);

        // Recalcular totales después de actualizar envíos
        $this->recalcularTotalesVenta($venta);
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

        foreach ($venta->detalles as $detalle) {
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

        // Log::info("Totales de venta recalculados", [
        //     'venta_id' => $venta->id,
        //     'referencia_shopify' => $venta->referencia_shopify,
        //     'subtotal' => round($subtotal, 2),
        //     'iva' => round($iva, 2),
        //     'gravada' => round($gravada, 2),
        //     'exenta' => round($exenta, 2),
        //     'total' => $total,
        //     'es_venta_shopify' => !empty($venta->referencia_shopify)
        // ]);
    }

    /**
     * Procesa el webhook de pedido actualizado de Shopify
     * 
     * @param string $tokenEmpresa
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function procesarVentaActualizada($tokenEmpresa, Request $request)
    {
        // Log::info("Webhook de pedido actualizado recibido de Shopify", [
        //     'shopify_order_id' => $request->id,
        //     'order_id' => $request->order_id ?? 'N/A',
        //     'order_edit_order_id' => $request->order_edit['order_id'] ?? 'N/A',
        //     'token_empresa' => $tokenEmpresa,
        //     'financial_status' => $request->financial_status ?? 'N/A',
        //     'fulfillment_status' => $request->fulfillment_status ?? 'N/A'
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            // Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
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
                ->first();

            // Si no se encuentra por ID, buscar por order_number
            if (!$venta && $orderNumber) {
                // Log::info("Buscando venta por order_number", [
                //     'order_number' => $orderNumber,
                //     'empresa_id' => $empresa->id
                // ]);
                
                $venta = Venta::where('referencia_shopify', 'SHOPIFY-' . $orderNumber)
                    ->where('id_empresa', $empresa->id)
                    ->first();
            }

            if (!$venta) {
                // Log::warning("Venta no encontrada para actualización", [
                //     'shopify_order_id' => $shopifyOrderId,
                //     'order_number' => $orderNumber,
                //     'referencia_buscada' => $referencia,
                //     'empresa_id' => $empresa->id
                // ]);
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
                Log::warning("Usuario no encontrado para actualizar venta", [
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
                if ($this->convertirCotizacionAVenta($venta, $empresa, $usuario, $request->all())) {
                    $venta->refresh();
                    $fueConvertida = true;
                }
            }

            // Verificar si la venta se creó hace menos de 10 segundos
            if ($venta->created_at->diffInSeconds(now()) < 10) {
                // Log::info("Venta recién creada, ignorando actualización inmediata", [
                //     'venta_id' => $venta->id,
                //     'created_at' => $venta->created_at,
                //     'shopify_order_id' => $request->id,
                //     'tiempo_transcurrido' => $venta->created_at->diffInSeconds(now()) . ' segundos'
                // ]);

                return response()->json([
                    'status' => 'success',
                    'mensaje' => $fueConvertida ? 'Cotización convertida a venta' : 'Actualización ignorada - venta recién creada',
                    'venta_id' => $venta->id
                ], 200);
            }

            // Actualizar estado de la venta si es necesario.
            // Se omite si la cotización acaba de convertirse en venta, porque la conversión ya
            // dejó el estado en 'Pagada' (evita que 'partially_paid' lo revierta a 'Pendiente').
            $nuevoEstado = $this->mapearEstado($financialStatus);
            
            if (!$fueConvertida && $venta->estado !== $nuevoEstado) {
                $observacion = 'Pedido actualizado en Shopify el ' . now()->format('d/m/Y H:i:s');
                
                // Agregar observación específica para reembolsos
                if ($financialStatus === 'refunded') {
                    $observacion = 'Pedido reembolsado en Shopify el ' . now()->format('d/m/Y H:i:s');
                }
                
                $venta->update([
                    'estado' => $nuevoEstado,
                    'observaciones_shopify' => ($venta->observaciones_shopify ? $venta->observaciones_shopify . ' | ' : '') . $observacion,
                ]);
                
                // Log::info("Estado de venta actualizado", [
                //     'venta_id' => $venta->id,
                //     'estado_anterior' => $venta->getOriginal('estado'),
                //     'estado_nuevo' => $nuevoEstado,
                //     'financial_status' => $financialStatus,
                //     'shopify_order_id' => $shopifyOrderId
                // ]);
            }

            // Actualizar envíos si han cambiado
            $this->actualizarEnvio($venta, $request, $usuario);

            // Actualizar cantidades de productos y crear productos nuevos si han cambiado
            $this->actualizarCantidadesProductos($venta, $request, $usuario);

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta actualizada correctamente',
                'venta_id' => $venta->id,
                'estado' => $venta->estado
            ], 200);

        } catch (\Exception $e) {
            Log::channel('shopify')->error('Error procesando actualización de venta desde Shopify: ' . $e->getMessage(), [
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
            Log::channel('shopify')->error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            Log::channel('shopify')->error("Usuario no encontrado", ['empresa_id' => $empresa->id]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        // Verificar que el usuario tenga bodega asignada
        if (!$usuario->id_bodega) {
            Log::channel('shopify')->error("Usuario sin bodega asignada", [
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
            Log::channel('shopify')->error("Ningún documento encontrado", [
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
                // Log::info("Draft Order duplicado detectado - orden ya procesada previamente", [
                //     'shopify_draft_order_id' => $request->id,
                //     'venta_id_existente' => $ventaExistente->id,
                //     'referencia_shopify' => $referenciaShopify,
                //     'webhook_id' => $request->header('X-Shopify-Webhook-Id'),
                //     'fecha_creacion_venta' => $ventaExistente->created_at
                // ]);

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
                        // Log::warning("Webhook duplicado detectado por webhook_id (Draft Order)", [
                        //     'shopify_draft_order_id' => $request->id,
                        //     'webhook_id' => $webhookId,
                        //     'referencia_shopify' => $referenciaShopify
                        // ]);

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
                    Log::channel('shopify')->warning("Cache no disponible para verificación de webhook duplicado (Draft Order) - continuando", [
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
                
                $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            } else {
                // Usar cliente "Consumidor Final" por defecto
                $cliente = ShopifyHelper::obtenerClienteConsumidorFinal($usuario->id_empresa);
                
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
            $ventaData['observaciones_shopify'] = 'Draft Order creado desde Shopify - ' . now()->format('d/m/Y H:i:s');

            $venta = Venta::create($ventaData);
            
            // Log::info("Draft Order creado como venta pendiente", [
            //     'venta_id' => $venta->id,
            //     'shopify_draft_order_id' => $request->id,
            //     'referencia' => $ventaData['referencia_shopify']
            // ]);

            // Procesar line items del draft order
            if (!empty($request->line_items)) {
                foreach ($request->line_items as $item) {
                    // Validar que el item tenga los datos mínimos necesarios
                    if (empty($item) || !is_array($item)) {
                        Log::channel('shopify')->warning("Line item inválido o vacío en draft order", ['item' => $item]);
                        continue;
                    }

                    // Log::info("Procesando line item de draft order", [
                    //     'variant_id' => $item['variant_id'] ?? 'N/A', 
                    //     'sku' => $item['sku'] ?? 'N/A',
                    //     'title' => $item['title'] ?? 'N/A'
                    // ]);
                    
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
                        // Log::info("Producto no encontrado en draft order, creando nuevo producto", [
                        //     'variant_id' => $item['variant_id'] ?? 'N/A',
                        //     'sku' => $item['sku'] ?? 'N/A',
                        //     'title' => $item['title'] ?? 'N/A'
                        // ]);
                        
                        $productoData = $this->transformer->transformarProducto(
                            $item,
                            $usuario->id_empresa,
                            $usuario->id,
                            $usuario->id_sucursal
                        );
                        $producto = Producto::create($productoData);
                        
                        // Log::info("Producto creado para draft order", ['producto_id' => $producto->id]);
                    }

                    // Crear detalle de venta
                    $taxesIncluded = $request->taxes_included ?? false;
                    $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id, $usuario->id_empresa, $taxesIncluded);
                    $detalleData['id_producto'] = $producto->id;
                    $venta->detalles()->create($detalleData);

                    // Log::info("Detalle de draft order creado", [
                    //     'venta_id' => $venta->id,
                    //     'producto_id' => $producto->id,
                    //     'cantidad' => $item['quantity'],
                    //     'precio' => $item['price']
                    // ]);
                }
            }

            // Procesar tipos de envío si existen
            // Los draft orders usan shipping_line (singular) en lugar de shipping_lines (plural)
            $shippingLines = $request->shipping_lines ?? [];
            if (empty($shippingLines) && !empty($request->shipping_line)) {
                $shippingLines = [$request->shipping_line];
            }
            
            if (!empty($shippingLines)) {
                // Log::info("Procesando tipos de envío en draft order", [
                //     'venta_id' => $venta->id,
                //     'shipping_lines_count' => count($shippingLines),
                //     'shipping_line_singular' => !empty($request->shipping_line),
                //     'shipping_lines_plural' => !empty($request->shipping_lines)
                // ]);

                $detallesEnvio = $this->shippingService->procesarTiposEnvio(
                    $shippingLines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );

                // Log::info("Detalles de envío procesados en draft order", [
                //     'venta_id' => $venta->id,
                //     'detalles_creados' => count($detallesEnvio)
                // ]);
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
            Log::channel('shopify')->error('Error procesando draft order de Shopify: ' . $e->getMessage(), [
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
        
        // Log::info('Mapeando canal de venta desde Shopify', [
        //     'shopify_channel' => $shopifyChannel,
        //     'empresa_id' => $empresaId,
        //     'shopify_order_id' => $request->id ?? 'N/A'
        // ]);

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

        // Log::info('Canal de venta mapeado', [
        //     'shopify_channel' => $shopifyChannel,
        //     'canal_id' => $canalId,
        //     'empresa_id' => $empresaId
        // ]);

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

            // Log::info('Canal de venta creado automáticamente', [
            //     'canal_id' => $canal->id,
            //     'nombre' => $nombreCanal,
            //     'empresa_id' => $empresaId
            // ]);
        }

        return $canal->id;
    }
}
