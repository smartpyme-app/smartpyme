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
            
            if (!$bodega) {
                Log::warning("Bodega no encontrada", [
                    'bodega_id' => $inventario->id_bodega
                ]);
                return;
            }

            // Primero obtenemos la empresa base para verificar si tiene WooCommerce habilitado
            $empresaBase = Empresa::where('id', $bodega->id_empresa)->first();
            
            if (!$empresaBase) {
                Log::warning("Empresa no encontrada para la bodega", [
                    'bodega_id' => $inventario->id_bodega,
                    'empresa_id' => $bodega->id_empresa
                ]);
                return;
            }

            // VALIDACIÓN PREVIA: Solo continuar si la empresa tiene intención de usar WooCommerce
            // Si no tiene ni siquiera woocommerce_status configurado o es null, no intentar sincronizar
            if (empty($empresaBase->woocommerce_status) || 
                $empresaBase->woocommerce_status === 'disconnected' || 
                $empresaBase->woocommerce_status === 'disabled') {
                
                Log::debug("Empresa sin integración WooCommerce habilitada - omitiendo sincronización", [
                    'bodega_id' => $inventario->id_bodega,
                    'empresa_id' => $empresaBase->id,
                    'empresa_nombre' => $empresaBase->nombre,
                    'woocommerce_status' => $empresaBase->woocommerce_status ?? 'null'
                ]);
                return;
            }

            // Solo si la empresa tiene status 'connecting' o 'connected', intentar la sincronización
            $empresa = Empresa::where('id', $bodega->id_empresa)
                ->whereNotNull('woocommerce_api_key')
                ->whereNotNull('woocommerce_store_url')
                ->whereNotNull('woocommerce_consumer_key')
                ->whereNotNull('woocommerce_consumer_secret')
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$empresa) {
                // Solo logear como INFO si la empresa está intentando conectarse
                if ($empresaBase->woocommerce_status === 'connecting') {
                    Log::info("Empresa en proceso de configuración WooCommerce - sincronización pendiente", [
                        'bodega_id' => $inventario->id_bodega,
                        'empresa_id' => $empresaBase->id,
                        'empresa_nombre' => $empresaBase->nombre,
                        'current_status' => $empresaBase->woocommerce_status,
                        'diagnostico' => 'Ejecuta: php artisan woocommerce:diagnosticar ' . $inventario->id_bodega
                    ]);
                } else {
                    // Solo logear como DEBUG para empresas con configuración incompleta
                    Log::debug("Configuración WooCommerce incompleta - omitiendo sincronización", [
                        'bodega_id' => $inventario->id_bodega,
                        'empresa_id' => $empresaBase->id,
                        'current_status' => $empresaBase->woocommerce_status
                    ]);
                }
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
