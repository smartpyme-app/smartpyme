<?php

namespace App\Observers;

use App\Models\Inventario\Inventario;
use App\Models\User;
use App\Services\WooCommerceStockService;
use Illuminate\Support\Facades\Log;

class InventarioObserver
{
    protected $stockService;

    public function __construct(WooCommerceStockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function updated(Inventario $inventario)
    {
        if ($inventario->isDirty('stock')) {
            Log::info("Cambio de stock detectado", [
                'producto_id' => $inventario->id_producto,
                'bodega_id' => $inventario->id_bodega,
                'stock_anterior' => $inventario->getOriginal('stock'),
                'stock_nuevo' => $inventario->stock
            ]);
            
            $usuarios = User::where('id_bodega', $inventario->id_bodega)
                                     ->whereNotNull('woocommerce_api_key')
                                     ->whereNotNull('woocommerce_store_url')
                                     ->whereNotNull('woocommerce_consumer_key')
                                     ->whereNotNull('woocommerce_consumer_secret')
                                     ->get();
            
            if ($usuarios->isEmpty()) {
                Log::info("No se encontraron usuarios con integración WooCommerce para esta bodega", [
                    'bodega_id' => $inventario->id_bodega
                ]);
                return;
            }
            
            foreach ($usuarios as $usuario) {
                try {
                    $this->stockService->actualizarStockEnWooCommerce(
                        $inventario->id_producto,
                        $usuario->id
                    );
                } catch (\Exception $e) {
                    Log::error("Error al sincronizar stock para usuario: " . $e->getMessage(), [
                        'usuario_id' => $usuario->id,
                        'producto_id' => $inventario->id_producto
                    ]);
                }
            }
        }
    }
    
    public function created(Inventario $inventario)
    {
        Log::info("Nuevo inventario creado", [
            'producto_id' => $inventario->id_producto,
            'bodega_id' => $inventario->id_bodega,
            'stock' => $inventario->stock
        ]);
        
        $usuarios = User::where('id_bodega', $inventario->id_bodega)
                                 ->whereNotNull('woocommerce_api_key')
                                 ->whereNotNull('woocommerce_store_url')
                                 ->whereNotNull('woocommerce_consumer_key')
                                 ->whereNotNull('woocommerce_consumer_secret')
                                 ->get();
        
        if ($usuarios->isEmpty()) {
            return;
        }
        
        foreach ($usuarios as $usuario) {
            try {
                $this->stockService->actualizarStockEnWooCommerce(
                    $inventario->id_producto,
                    $usuario->id
                );
            } catch (\Exception $e) {
                Log::error("Error al sincronizar stock para nuevo inventario: " . $e->getMessage());
            }
        }
    }
}