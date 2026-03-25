<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ShopifyStockService;
use App\Services\ShopifySyncCache;
use Illuminate\Support\Facades\Log;

class ShopifyProductoObserver
{
    protected $stockService;
    protected $cache;

    public function __construct(ShopifyStockService $stockService, ShopifySyncCache $cache)
    {
        $this->stockService = $stockService;
        $this->cache = $cache;
    }


    // SINCRONIZACIÓN INVERSA DESHABILITADA: Solo sincronización unidireccional (Shopify -> SmartPyme)
    public function created(Producto $producto)
    {
        $empresa = Empresa::where('id', $producto->id_empresa)->first();
        if (!$empresa) {
            return;
        }

        if ($empresa->shopify_sync_bidirectional) {
            Log::info("Sincronización inversa habilitada para productos nuevos - SmartPyme -> Shopify ", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'motivo' => 'Sincronización unidireccional configurada'
            ]);
            $this->createdSyncBidirectional($producto);
        }

        Log::info("Sincronización inversa deshabilitada para productos nuevos - solo Shopify -> SmartPyme", [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'motivo' => 'Sincronización unidireccional configurada'
        ]);
        return;
    }

    // Para sincronización doble direccional (SmartPyme -> Shopify)
    public function createdSyncBidirectional(Producto $producto)
    {
        // PREVENIR CICLO: No sincronizar productos que vienen de Shopify
        if ($producto->shopify_product_id || $producto->syncing_from_shopify) {
            return;
        }

        // Verificar si está en proceso de sincronización desde webhook
        if ($this->cache->isLocked($producto->id)) {
            return;
        }

        $empresa = Empresa::where('id', $producto->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) return;

        $usuarios = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->get();

        foreach ($usuarios as $usuario) {
            $success = $this->stockService->createdProductoCompletoEnShopify(
                $producto->id,
                $usuario->id,
                true
            );

            if ($success) {
                $this->cache->saveProductSnapshot($producto);
            }
        }
    }

    // SINCRONIZACIÓN INVERSA DESHABILITADA: Solo sincronización unidireccional (Shopify -> SmartPyme)
    public function updated(Producto $producto)
    {

        $empresa = Empresa::where('id', $producto->id_empresa)->first();
        if (!$empresa) {
            return;
        }

        if ($empresa->shopify_sync_bidirectional) {
            Log::info("Sincronización inversa habilitada para actualizaciones de productos - SmartPyme -> Shopify ", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'motivo' => 'Sincronización unidireccional configurada'
            ]);
            $this->updatedSyncBidirectional($producto);
        }

        return;
    }

    // Para sincronización doble direccional (SmartPyme -> Shopify)
    public function updatedSyncBidirectional(Producto $producto)
    {
        if (!$producto->enable) {
            return;
        }

        // PREVENIR CICLO: No sincronizar productos que están siendo actualizados desde Shopify
        if ($producto->syncing_from_shopify) {
            Log::info("Producto siendo sincronizado desde Shopify, omitiendo sincronización inversa", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'syncing_from_shopify' => $producto->syncing_from_shopify
            ]);
            return;
        }

        if ($this->cache->isLocked($producto->id)) {
            return;
        }

        // EXCLUIR 'precio' de la sincronización - Shopify es la fuente de verdad para precios
        $camposRelevantes = ['costo', 'codigo', 'nombre', 'descripcion', 'id_categoria'];

        // Verificar si solo cambió el precio (no sincronizar)
        if ($producto->isDirty('precio') && !$producto->isDirty($camposRelevantes)) {
            Log::info("Cambio de precio detectado - no sincronizando (Shopify es fuente de verdad)", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio_anterior' => $producto->getOriginal('precio'),
                'precio_nuevo' => $producto->precio
            ]);
            return;
        }

        // Cargar las imágenes para verificar cambios (incluyendo cambios en imágenes)
        if (!$producto->relationLoaded('imagenes')) {
            $producto->load('imagenes');
        }

        // Verificar cambios en campos directos
        $hayCambiosEnCampos = false;
        foreach ($camposRelevantes as $campo) {
            if ($producto->isDirty($campo)) {
                $hayCambiosEnCampos = true;
                break;
            }
        }

        // Verificar cambios en el producto (incluyendo imágenes) comparando con el cache
        // Esto detectará cambios incluso si solo se agregaron/eliminaron imágenes
        $hayCambios = $this->cache->hasProductChanged($producto);
        
        // Si no hay cambios en campos directos ni en imágenes, no sincronizar
        if (!$hayCambiosEnCampos && !$hayCambios) {
            Log::info("No hay cambios relevantes en el producto", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre
            ]);
            return;
        }

        // Si hay cambios pero solo en imágenes, loguear para debugging
        if ($hayCambios && !$hayCambiosEnCampos) {
            Log::info("Cambios detectados solo en imágenes del producto", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'imagenes_count' => $producto->imagenes->count()
            ]);
        }

        $empresa = Empresa::where('id', $producto->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) return;

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) return;

        // SINCRONIZAR A SHOPIFY: Tanto productos locales como productos que vinieron de Shopify
        // pero que ahora se están editando desde el sistema local
        $success = $this->stockService->actualizarProductoCompletoEnShopify(
            $producto->id,
            $usuario->id,
            false
        );

        if ($success) {
            $this->cache->saveProductSnapshot($producto);
        }
    }


}
