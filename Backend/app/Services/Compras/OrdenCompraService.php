<?php

namespace App\Services\Compras;

use App\Models\Compras\Compra;
use App\Models\OrdenCompra;
use Illuminate\Support\Facades\Log;

class OrdenCompraService
{
    /**
     * Actualiza la orden de compra relacionada cuando se factura una compra
     *
     * @param Compra $compra La compra que se está facturando
     * @param array $detalles Los detalles de la compra (array de detalles con id_producto y cantidad)
     * @return void
     */
    public function actualizarDesdeCompra(Compra $compra, array $detalles): void
    {
        // Si la compra no tiene orden de compra asociada, no hacer nada
        if (!$compra->num_orden_compra) {
            return;
        }

        Log::info("Actualizando orden de compra desde facturación", [
            'compra_id' => $compra->id,
            'orden_compra_id' => $compra->num_orden_compra
        ]);

        // Obtener la orden de compra con sus detalles
        $orden = OrdenCompra::where('id', $compra->num_orden_compra)
            ->with('detalles')
            ->first();

        if (!$orden) {
            Log::warning("Orden de compra no encontrada", [
                'orden_compra_id' => $compra->num_orden_compra
            ]);
            return;
        }

        // Convertir detalles a colección para facilitar búsquedas
        $detCollection = collect($detalles);
        
        // Flag para determinar si la orden está completa
        $finishOrder = true;

        // Actualizar cantidad procesada en cada detalle de la orden
        foreach ($orden->detalles as $detalleOrden) {
            // Buscar el detalle correspondiente en la compra por id_producto
            $detalleCompra = $detCollection->where('id_producto', $detalleOrden->id_producto)->first();

            if ($detalleCompra) {
                // Incrementar cantidad procesada
                $cantidadProcesadaAnterior = $detalleOrden->cantidad_procesada ?? 0;
                $detalleOrden->cantidad_procesada = $cantidadProcesadaAnterior + $detalleCompra['cantidad'];
                $detalleOrden->save();

                Log::info("Detalle de orden actualizado", [
                    'detalle_orden_id' => $detalleOrden->id,
                    'id_producto' => $detalleOrden->id_producto,
                    'cantidad_anterior' => $cantidadProcesadaAnterior,
                    'cantidad_agregada' => $detalleCompra['cantidad'],
                    'cantidad_procesada_nueva' => $detalleOrden->cantidad_procesada,
                    'cantidad_total' => $detalleOrden->cantidad
                ]);

                // Verificar si este detalle está completo
                if ($detalleOrden->cantidad_procesada < $detalleOrden->cantidad) {
                    $finishOrder = false;
                }
            }
        }

        // Verificar si todos los productos de la orden están en la compra
        // Si el número de detalles de la compra no coincide con el de la orden, no está completa
        if ($detCollection->count() != $orden->detalles->count()) {
            $finishOrder = false;
        }

        // Si la orden está completa, marcarla como 'Aceptada'
        if ($finishOrder) {
            $orden->estado = 'Aceptada';
            Log::info("Orden de compra marcada como aceptada", [
                'orden_compra_id' => $orden->id
            ]);
        }

        $orden->save();
    }
}

