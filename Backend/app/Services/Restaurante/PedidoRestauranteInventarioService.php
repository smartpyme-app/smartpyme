<?php

namespace App\Services\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\PedidoRestaurante;

class PedidoRestauranteInventarioService
{
    /**
     * Descuenta inventario/kardex al pasar el pedido a pendiente de facturar.
     * Idempotente si ya existe inventario_descontado_at.
     */
    public function aplicarAlConfirmar(PedidoRestaurante $pedido, $user): ?string
    {
        if ($pedido->inventario_descontado_at) {
            return null;
        }

        $idBodega = $user->id_bodega ?? null;
        if (! $idBodega) {
            return 'El usuario no tiene bodega asignada; no se puede descontar inventario.';
        }

        $empresa = Empresa::find($user->id_empresa);
        if (! $empresa) {
            return 'Empresa no encontrada.';
        }

        $puedeVenderSinStock = $empresa->vender_sin_stock == 1;
        $lotesActivo = $empresa->isLotesActivo();

        $pedido->load(['detalles']);

        foreach ($pedido->detalles as $detalle) {
            $producto = Producto::find($detalle->producto_id);
            if (! $producto || $producto->tipo === 'Servicio') {
                continue;
            }

            $cantidad = (float) $detalle->cantidad;
            $precio = (float) $detalle->precio;

            $inventario = Inventario::where('id_producto', $detalle->producto_id)
                ->where('id_bodega', $idBodega)
                ->first();

            if ($producto->inventario_por_lotes && $lotesActivo) {
                $metodologia = $empresa->getLotesMetodologia();
                if ($metodologia === 'Manual') {
                    return 'Los pedidos canal no admiten productos con lotes en metodología Manual. Use FIFO/LIFO/FEFO o productos sin lotes.';
                }

                $lotesQuery = Lote::where('id_producto', $detalle->producto_id)
                    ->where('id_bodega', $idBodega)
                    ->where('stock', '>', 0);

                $loteSeleccionado = null;
                switch ($metodologia) {
                    case 'FIFO':
                        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();
                        break;
                    case 'LIFO':
                        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'desc')->first();
                        break;
                    case 'FEFO':
                        $loteSeleccionado = (clone $lotesQuery)
                            ->whereNotNull('fecha_vencimiento')
                            ->orderBy('fecha_vencimiento', 'asc')
                            ->first();
                        if (! $loteSeleccionado) {
                            $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();
                        }
                        break;
                    default:
                        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();
                }

                if (! $loteSeleccionado) {
                    if (! $puedeVenderSinStock) {
                        return 'No hay lotes disponibles con stock para el producto: ' . $producto->nombre;
                    }

                    continue;
                }

                if ($loteSeleccionado->stock < $cantidad && ! $puedeVenderSinStock) {
                    $num = $loteSeleccionado->numero_lote ?? '';

                    return 'No hay suficiente stock en el lote ' . $num . ' para el producto: ' . $producto->nombre;
                }

                $loteSeleccionado->stock -= $cantidad;
                $loteSeleccionado->save();

                $detalle->lote_id = $loteSeleccionado->id;
                $detalle->save();

                if ($inventario) {
                    $inventario->stock -= $cantidad;
                    $inventario->save();
                    $inventario->kardex($pedido, $cantidad, $precio, null, null, ['id_usuario' => $user->id]);
                }
            } else {
                if ($inventario) {
                    $stockDisponible = $inventario->stock;
                    if (! $puedeVenderSinStock && $stockDisponible < $cantidad) {
                        return "No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$stockDisponible}, Cantidad: {$cantidad}";
                    }
                    $inventario->stock -= $cantidad;
                    $inventario->save();
                    $inventario->kardex($pedido, $cantidad, $precio, null, null, ['id_usuario' => $user->id]);
                } elseif (! $puedeVenderSinStock) {
                    return "No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada.";
                }
            }
        }

        $pedido->inventario_descontado_at = now();
        $pedido->id_bodega_inventario = $idBodega;
        $pedido->save();

        return null;
    }

    /**
     * Devuelve stock/kardex si el pedido ya había descontado inventario (anulación desde pendiente_facturar).
     */
    public function revertirPorAnulacion(PedidoRestaurante $pedido, $userForKardex = null): ?string
    {
        if (! $pedido->inventario_descontado_at) {
            return null;
        }

        $idBodega = $pedido->id_bodega_inventario;
        if (! $idBodega) {
            $pedido->inventario_descontado_at = null;
            $pedido->save();

            return null;
        }

        $pedido->load('detalles');
        $empresa = Empresa::find($pedido->id_empresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
        $idUsuario = $userForKardex->id ?? $pedido->usuario_id;

        foreach ($pedido->detalles as $detalle) {
            $producto = Producto::find($detalle->producto_id);
            if (! $producto || $producto->tipo === 'Servicio') {
                continue;
            }

            $cantidad = (float) $detalle->cantidad;
            $precio = (float) $detalle->precio;

            if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
                $lote = Lote::find($detalle->lote_id);
                if ($lote) {
                    $lote->stock += $cantidad;
                    $lote->save();
                }
            }

            $inventario = Inventario::where('id_producto', $detalle->producto_id)
                ->where('id_bodega', $idBodega)
                ->first();

            if ($inventario) {
                $inventario->stock += $cantidad;
                $inventario->save();
                $inventario->kardex($pedido, -$cantidad, $precio, null, null, ['id_usuario' => $idUsuario]);
            }

            if ($detalle->lote_id) {
                $detalle->lote_id = null;
                $detalle->save();
            }
        }

        $pedido->inventario_descontado_at = null;
        $pedido->id_bodega_inventario = null;
        $pedido->save();

        return null;
    }
}
