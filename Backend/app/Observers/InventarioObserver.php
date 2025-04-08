<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
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

            $bodega = Bodega::where('id', $inventario->id_bodega)->first();
            $empresa = Empresa::where('id', $bodega->id_empresa)
                ->whereNotNull('woocommerce_api_key')
                ->whereNotNull('woocommerce_store_url')
                ->whereNotNull('woocommerce_consumer_key')
                ->whereNotNull('woocommerce_consumer_secret')
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$empresa) {
                Log::info("No se encontró empresa con integración WooCommerce para esta bodega", [
                    'bodega_id' => $inventario->id_bodega
                ]);
                return;
            }


            $usuario = User::where('id_empresa', $empresa->id)
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$usuario) {
                Log::info("No se encontró usuario con integración WooCommerce para esta empresa", [
                    'empresa_id' => $empresa->id
                ]);
                return;
            }

            if ($inventario->id_bodega != $usuario->id_bodega) {
                return;
            }


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

    // public function created(Inventario $inventario)
    // {
    //     Log::info("Nuevo inventario creado", [
    //         'producto_id' => $inventario->id_producto,
    //         'bodega_id' => $inventario->id_bodega,
    //         'stock' => $inventario->stock
    //     ]);

    //     $bodegas = Bodega::where('id', $inventario->id_bodega)->get();



    //     $usuarios = User::where('id_sucursal', $bodegas->pluck('id_sucursal')->toArray())
    //                              ->whereNotNull('woocommerce_api_key')
    //                              ->whereNotNull('woocommerce_store_url')
    //                              ->whereNotNull('woocommerce_consumer_key')
    //                              ->whereNotNull('woocommerce_consumer_secret')
    //                              ->where('woocommerce_status', 'connected')
    //                              ->get();

    //     if ($usuarios->isEmpty()) {
    //         return;
    //     }

    //     foreach ($usuarios as $usuario) {
    //         try {
    //             $this->stockService->actualizarStockEnWooCommerce(
    //                 $inventario->id_producto,
    //                 $usuario->id
    //             );
    //         } catch (\Exception $e) {
    //             Log::error("Error al sincronizar stock para nuevo inventario: " . $e->getMessage());
    //         }
    //     }
    // }
}