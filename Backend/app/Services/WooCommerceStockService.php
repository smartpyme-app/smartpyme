<?php

namespace App\Services;

use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WooCommerceStockService
{
   
    public function actualizarStockEnWooCommerce($productoId, $userId)
    {
        try {
  
            $usuario = User::find($userId);

            if (!$usuario || empty($usuario->woocommerce_api_key)) {
                Log::error("Usuario no encontrado o sin API key de WooCommerce", ['user_id' => $userId]);
                return false;
            }

           
            if (
                empty($usuario->woocommerce_store_url) ||
                empty($usuario->woocommerce_consumer_key) ||
                empty($usuario->woocommerce_consumer_secret)
            ) {

                Log::error("Usuario sin credenciales completas de WooCommerce", ['user_id' => $userId]);
                return false;
            }

    
            $producto = Producto::find($productoId);

            if (!$producto) {
                Log::error("Producto no encontrado", ['producto_id' => $productoId]);
                return false;
            }

            if (empty($producto->codigo)) {
                Log::warning("Producto sin código/SKU para buscar en WooCommerce", [
                    'producto_id' => $productoId,
                    'nombre' => $producto->nombre
                ]);
                return false;
            }

            $sku = $producto->codigo;
            $bodegas = Bodega::where('id_sucursal', $usuario->id_sucursal)->get();

            $stock = Inventario::where('id_producto', $productoId)
                ->whereIn('id_bodega', $bodegas->pluck('id'))
                ->value('stock');

            if ($stock === null) {
                Log::warning("No se encontró inventario para el producto", ['producto_id' => $productoId]);
                $stock = 0;
            }

            $wooClient = new WooCommerceApiClient(
                $usuario->woocommerce_store_url,
                $usuario->woocommerce_consumer_key,
                $usuario->woocommerce_consumer_secret
            );


            $searchResponse = $wooClient->get('products', [
                'sku' => $sku,
                'per_page' => 1
            ]);

            Log::info("Respuesta de WooCommerce: ", $searchResponse);


            if ($searchResponse['status'] !== 'success' || count($searchResponse['body']) === 0) {
                Log::warning("No se encontró producto en WooCommerce con SKU: {$sku}", [
                    'response' => $searchResponse
                ]);
                return false;
            }

            //$wooProductId = $searchResponse[0]['id'];

            $wooProductId = $searchResponse['body'][0]['id'];

            $response = $wooClient->put('products/' . $wooProductId, [
                'stock_quantity' => (int)$stock,
                'manage_stock' => true
            ]);

            Log::info("Stock actualizado en WooCommerce", [
                'producto_id' => $productoId,
                'woocommerce_id' => $wooProductId,
                'sku' => $sku,
                'stock' => $stock,
                'response' => $response
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Error al actualizar stock en WooCommerce: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    public function sincronizarMultiplesProductos(array $productoIds, $userId)
    {
        $resultados = [
            'total' => count($productoIds),
            'exitosos' => 0,
            'fallidos' => 0,
            'detalles' => []
        ];

        foreach ($productoIds as $productoId) {
            $exito = $this->actualizarStockEnWooCommerce($productoId, $userId);

            if ($exito) {
                $resultados['exitosos']++;
            } else {
                $resultados['fallidos']++;
            }

            $resultados['detalles'][] = [
                'producto_id' => $productoId,
                'resultado' => $exito ? 'exitoso' : 'fallido'
            ];
        }

        return $resultados;
    }
}
