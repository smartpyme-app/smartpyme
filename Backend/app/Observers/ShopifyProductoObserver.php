<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ShopifyStockService;
use Illuminate\Support\Facades\Log;

class ShopifyProductoObserver
{
    protected $stockService;

    public function __construct(ShopifyStockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function updated(Producto $producto)
    {
        Log::info("Producto actualizado", [
            'producto_id' => $producto->id
        ]);
        // No sincronizar si el producto está deshabilitado
        if (!$producto->enable) {
            Log::info("Producto deshabilitado, no se sincronizará con Shopify", [
                'producto_id' => $producto->id
            ]);
            return;
        }

        $empresa = Empresa::where('id', $producto->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            return;
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            return;
        }

        try {
            $this->stockService->actualizarStockEnShopify(
                $producto->id,
                $usuario->id
            );
        } catch (\Exception $e) {
            Log::error("Error al sincronizar producto Shopify para usuario: " . $e->getMessage(), [
                'usuario_id' => $usuario->id,
                'producto_id' => $producto->id
            ]);
        }
    }

    public function created(Producto $producto)
    {
        Log::info("Producto creado", [
            'producto_id' => $producto->id
        ]);
        $empresa = Empresa::where('id', $producto->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            return;
        }

        $usuarios = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->get();

        if ($usuarios->isEmpty()) {
            Log::info("No se encontraron usuarios con integración Shopify para este producto", [
                'producto_id' => $producto->id,
                'sucursal_id' => $producto->id_sucursal
            ]);
            return;
        }

        foreach ($usuarios as $usuario) {
            try {
                $this->stockService->actualizarStockEnShopify(
                    $producto->id,
                    $usuario->id
                );
            } catch (\Exception $e) {
                Log::error("Error al sincronizar nuevo producto Shopify para usuario: " . $e->getMessage(), [
                    'usuario_id' => $usuario->id,
                    'producto_id' => $producto->id
                ]);
            }
        }

        Log::info("Usuarios con Shopify encontrados", [
            'usuarios' => $usuarios->count()
        ]);
    }
}