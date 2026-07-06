<?php

namespace App\Services\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\PedidoDetalleLote;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\Restaurante\PedidoRestauranteDetalle;
use App\Services\Inventario\LoteAsignacionService;
use RuntimeException;

class PedidoCanalInventarioService
{
    public function kardexOpcionesUsuario(PedidoRestaurante $pedido): array
    {
        return ['id_usuario' => $pedido->usuario_id];
    }

    /**
     * Descuenta inventario y registra kardex al confirmar el pedido (pendiente de facturar).
     *
     * @throws RuntimeException
     */
    public function aplicarSalidasAlConfirmar(PedidoRestaurante $pedido, int $idBodega): void
    {
        $empresa = Empresa::findOrFail($pedido->id_empresa);
        $puedeVenderSinStock = $empresa->vender_sin_stock == 1;
        $lotesActivo = $empresa->isLotesActivo();
        $metodologia = $empresa->getLotesMetodologia();

        $pedido->load(['detalles', 'detalles.producto', 'detalles.producto.composiciones']);

        foreach ($pedido->detalles as $detalleRow) {
            $detalleRow->meta_inventario = null;

            $producto = $detalleRow->producto ?: Producto::find($detalleRow->producto_id);
            if (!$producto) {
                continue;
            }

            $cantidad = (float) $detalleRow->cantidad;
            $precioLinea = (float) $detalleRow->precio;
            $kardexOpts = $this->kardexOpcionesUsuario($pedido);

            if ($producto->tipo === 'Servicio') {
                $detalleRow->lote_id = null;
                $detalleRow->save();
                $this->procesarComposiciones(
                    $pedido,
                    $detalleRow,
                    $producto,
                    $cantidad,
                    $idBodega,
                    $empresa,
                    $puedeVenderSinStock,
                    $lotesActivo,
                    $metodologia
                );

                continue;
            }

            $inventario = Inventario::where('id_producto', $detalleRow->producto_id)
                ->where('id_bodega', $idBodega)->first();

            if (!$puedeVenderSinStock) {
                if ($inventario) {
                    if ($inventario->stock < $cantidad) {
                        throw new RuntimeException(
                            "No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$inventario->stock}, Cantidad requerida: {$cantidad}"
                        );
                    }
                } else {
                    throw new RuntimeException(
                        "No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada"
                    );
                }
            }

            $registrosLotes = PedidoDetalleLote::where('pedido_detalle_id', $detalleRow->id)->get();
            $asignacionManual = $registrosLotes->map(fn ($r) => [
                'lote_id' => (int) $r->lote_id,
                'cantidad' => (float) $r->cantidad,
            ])->all();

            if ($producto->inventario_por_lotes && $lotesActivo) {
                try {
                    $asignaciones = LoteAsignacionService::resolverAsignacionesSalida(
                        $producto,
                        $idBodega,
                        $cantidad,
                        $metodologia,
                        $lotesActivo,
                        $detalleRow->lote_id ? (int) $detalleRow->lote_id : null,
                        !empty($asignacionManual) ? $asignacionManual : null
                    );
                } catch (RuntimeException $e) {
                    if (!$puedeVenderSinStock) {
                        throw $e;
                    }
                    $asignaciones = [];
                }

                if (!empty($asignaciones) && $inventario) {
                    LoteAsignacionService::aplicarSalidaDocumento(
                        $asignaciones,
                        $pedido,
                        $inventario,
                        $precioLinea,
                        $kardexOpts
                    );
                    LoteAsignacionService::sincronizarPedidoDetalleLotes($detalleRow->id, $asignaciones);
                    $detalleRow->lote_id = count($asignaciones) === 1 ? $asignaciones[0]['lote_id'] : null;
                    $detalleRow->save();
                } elseif (!$puedeVenderSinStock && empty($asignaciones)) {
                    if ($metodologia === 'Manual') {
                        throw new RuntimeException(
                            "Debe seleccionar lotes para el producto: {$producto->nombre} (metodología Manual)."
                        );
                    }
                    throw new RuntimeException(
                        "No hay lotes disponibles con stock para el producto: {$producto->nombre}"
                    );
                }
            } elseif ($inventario) {
                $inventario->stock -= $cantidad;
                $inventario->save();
                $inventario->kardex($pedido, $cantidad, $precioLinea, null, null, $kardexOpts);
            }

            $this->procesarComposiciones(
                $pedido,
                $detalleRow,
                $producto,
                $cantidad,
                $idBodega,
                $empresa,
                $puedeVenderSinStock,
                $lotesActivo,
                $metodologia
            );
        }
    }

    /**
     * Restaura inventario y kardex (anulación en pendiente de facturar).
     */
    public function revertirSalidasPedido(PedidoRestaurante $pedido, int $idBodega): void
    {
        $pedido->load(['detalles', 'detalles.producto', 'detalles.producto.composiciones']);

        foreach ($pedido->detalles as $detalleRow) {
            $producto = $detalleRow->producto ?: Producto::find($detalleRow->producto_id);
            if (!$producto) {
                continue;
            }

            $cantidad = (float) $detalleRow->cantidad;
            $precioLinea = (float) $detalleRow->precio;

            $meta = $detalleRow->meta_inventario;
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            $compLista = is_array($meta) ? ($meta['componentes'] ?? []) : [];

            foreach ($compLista as $c) {
                $idComp = (int) ($c['id_compuesto'] ?? 0);
                $loteId = isset($c['lote_id']) ? (int) $c['lote_id'] : null;
                if (!$idComp) {
                    continue;
                }
                $qtyComp = $cantidad * $this->factorCompuestoEnProducto($producto, $idComp);
                if ($qtyComp <= 0) {
                    continue;
                }
                if ($loteId) {
                    $l = Lote::find($loteId);
                    if ($l) {
                        $l->stock += $qtyComp;
                        $l->save();
                    }
                }
                $inv = Inventario::where('id_producto', $idComp)->where('id_bodega', $idBodega)->first();
                if ($inv) {
                    $inv->stock += $qtyComp;
                    $inv->save();
                    $inv->kardex($pedido, -$qtyComp, null, null, null, $this->kardexOpcionesUsuario($pedido));
                }
            }

            if ($producto->tipo === 'Servicio') {
                $detalleRow->lote_id = null;
                $detalleRow->meta_inventario = null;
                $detalleRow->save();

                continue;
            }

            $inventario = Inventario::where('id_producto', $detalleRow->producto_id)
                ->where('id_bodega', $idBodega)->first();

            $registrosLotes = PedidoDetalleLote::where('pedido_detalle_id', $detalleRow->id)->get();

            if ($registrosLotes->isNotEmpty() && $inventario) {
                LoteAsignacionService::revertirSalidaDocumento(
                    $registrosLotes,
                    $pedido,
                    $inventario,
                    $cantidad,
                    $detalleRow->lote_id,
                    $precioLinea,
                    $this->kardexOpcionesUsuario($pedido)
                );
            } elseif ($detalleRow->lote_id && $inventario) {
                LoteAsignacionService::revertirSalidaDocumento(
                    [],
                    $pedido,
                    $inventario,
                    $cantidad,
                    (int) $detalleRow->lote_id,
                    $precioLinea,
                    $this->kardexOpcionesUsuario($pedido)
                );
            } elseif ($inventario) {
                $inventario->stock += $cantidad;
                $inventario->save();
                $inventario->kardex($pedido, -$cantidad, $precioLinea, null, null, $this->kardexOpcionesUsuario($pedido));
            }

            PedidoDetalleLote::where('pedido_detalle_id', $detalleRow->id)->delete();
            $detalleRow->lote_id = null;
            $detalleRow->meta_inventario = null;
            $detalleRow->save();
        }
    }

    public static function ventaCoincideConPedido(PedidoRestaurante $pedido, array $detallesVenta): bool
    {
        $pedido->loadMissing('detalles');
        $pedidoSums = $pedido->detalles->groupBy('producto_id')->map(fn ($g) => round($g->sum('cantidad'), 4));
        $ventaSums = collect($detallesVenta)->groupBy('id_producto')->map(fn ($g) => round(collect($g)->sum(fn ($d) => (float) ($d['cantidad'] ?? 0)), 4));

        $pKeys = $pedidoSums->keys()->map(fn ($k) => (int) $k)->sort()->values()->all();
        $vKeys = $ventaSums->keys()->map(fn ($k) => (int) $k)->sort()->values()->all();
        if ($pKeys !== $vKeys) {
            return false;
        }
        foreach ($pedidoSums as $pid => $qty) {
            if (!isset($ventaSums[$pid]) || abs($qty - $ventaSums[$pid]) > 0.0001) {
                return false;
            }
        }

        return true;
    }

    private function seleccionarLotePrincipal(int $idProducto, int $idBodega, string $metodologia): ?Lote
    {
        if ($metodologia === 'Manual') {
            return null;
        }
        $lotesQuery = Lote::where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->where('stock', '>', 0);

        switch ($metodologia) {
            case 'LIFO':
                return (clone $lotesQuery)->orderBy('created_at', 'desc')->first();
            case 'FEFO':
                $sel = (clone $lotesQuery)->whereNotNull('fecha_vencimiento')
                    ->orderBy('fecha_vencimiento', 'asc')->first();
                if (!$sel) {
                    return $lotesQuery->orderBy('created_at', 'asc')->first();
                }

                return $sel;
            case 'FIFO':
            default:
                return $lotesQuery->orderBy('created_at', 'asc')->first();
        }
    }

    private function procesarComposiciones(
        PedidoRestaurante $pedido,
        PedidoRestauranteDetalle $detalleRow,
        Producto $producto,
        float $cantidadLinea,
        int $idBodega,
        Empresa $empresa,
        bool $puedeVenderSinStock,
        bool $lotesActivo,
        string $metodologia
    ): void {
        $composiciones = $producto->composiciones;
        if ($composiciones->isEmpty()) {
            return;
        }

        $compMeta = [];

        foreach ($composiciones as $comp) {
            $idComp = (int) $comp->id_compuesto;
            $cantidadCompRequerida = $cantidadLinea * (float) $comp->cantidad;

            $productoCompuesto = Producto::where('id', $idComp)->first();
            if (!$productoCompuesto || $productoCompuesto->tipo === 'Servicio') {
                continue;
            }

            $loteCompuesto = null;
            if ($productoCompuesto->inventario_por_lotes && $lotesActivo) {
                $loteCompuesto = $this->seleccionarLotePrincipal(
                    $idComp,
                    $idBodega,
                    $metodologia
                );

                if ($loteCompuesto) {
                    if (!$puedeVenderSinStock && $loteCompuesto->stock < $cantidadCompRequerida) {
                        throw new RuntimeException(
                            "No hay suficiente stock en el lote {$loteCompuesto->numero_lote} del producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$loteCompuesto->stock}, Cantidad requerida: {$cantidadCompRequerida}"
                        );
                    }
                    $loteCompuesto->stock -= $cantidadCompRequerida;
                    $loteCompuesto->save();
                } else {
                    if ($metodologia === 'Manual') {
                        throw new RuntimeException(
                            "La metodología Manual de lotes no permite confirmar automáticamente el compuesto {$productoCompuesto->nombre} en pedidos de canal."
                        );
                    }
                    $inventarioComp = Inventario::where('id_producto', $idComp)
                        ->where('id_bodega', $idBodega)->first();
                    if (!$puedeVenderSinStock) {
                        if (!$inventarioComp || $inventarioComp->stock < $cantidadCompRequerida) {
                            $disp = $inventarioComp->stock ?? 0;
                            throw new RuntimeException(
                                "No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$disp}, Cantidad requerida: {$cantidadCompRequerida}"
                            );
                        }
                    }
                }
            } else {
                $inventarioComp = Inventario::where('id_producto', $idComp)
                    ->where('id_bodega', $idBodega)->first();
                if (!$puedeVenderSinStock) {
                    if ($inventarioComp) {
                        if ($inventarioComp->stock < $cantidadCompRequerida) {
                            throw new RuntimeException(
                                "No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$inventarioComp->stock}, Cantidad requerida: {$cantidadCompRequerida}"
                            );
                        }
                    } else {
                        throw new RuntimeException(
                            "No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada"
                        );
                    }
                }
            }

            $inventario = Inventario::where('id_producto', $idComp)
                ->where('id_bodega', $idBodega)->first();

            if ($inventario) {
                $inventario->stock -= $cantidadCompRequerida;
                $inventario->save();
                $inventario->kardex($pedido, $cantidadCompRequerida, null, null, null, $this->kardexOpcionesUsuario($pedido));
            }

            $compMeta[] = [
                'id_compuesto' => $idComp,
                'lote_id' => $loteCompuesto ? $loteCompuesto->id : null,
            ];
        }

        if (!empty($compMeta)) {
            $detalleRow->meta_inventario = ['componentes' => $compMeta];
            $detalleRow->save();
        }
    }

    private function factorCompuestoEnProducto(Producto $producto, int $idCompuesto): float
    {
        if ($producto->relationLoaded('composiciones')) {
            foreach ($producto->composiciones as $comp) {
                if ((int) $comp->id_compuesto === $idCompuesto) {
                    return (float) $comp->cantidad;
                }
            }
        } else {
            $comp = $producto->composiciones()->where('id_compuesto', $idCompuesto)->first();
            if ($comp) {
                return (float) $comp->cantidad;
            }
        }

        return 0;
    }
}
