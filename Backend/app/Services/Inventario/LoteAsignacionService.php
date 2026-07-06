<?php

namespace App\Services\Inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Salidas\DetalleSalidaLote;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\TrasladoLote;
use App\Models\Restaurante\PedidoDetalleLote;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleVentaLote;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class LoteAsignacionService
{
    /**
     * Distribuye una cantidad (unidades base) entre uno o más lotes.
     *
     * @param  array<int, array{lote_id:int, cantidad:float|int|string}>|null  $asignacionManual
     * @return array<int, array{lote_id:int, cantidad:float, lote:Lote}>
     */
    public static function distribuir(
        int $idProducto,
        int $idBodega,
        float $cantidadBase,
        string $metodologia,
        ?int $lotePreferidoId = null,
        ?array $asignacionManual = null
    ): array {
        if ($cantidadBase <= 0) {
            return [];
        }

        if (!empty($asignacionManual)) {
            return self::validarAsignacionManual($asignacionManual, $idProducto, $idBodega, $cantidadBase);
        }

        if ($metodologia === 'Manual') {
            if (!$lotePreferidoId) {
                throw new RuntimeException('Debe seleccionar un lote (metodología Manual).');
            }

            return self::distribuirDesdeLoteUnico($lotePreferidoId, $idProducto, $idBodega, $cantidadBase);
        }

        return self::distribuirAutomatico($idProducto, $idBodega, $cantidadBase, $metodologia, $lotePreferidoId);
    }

    /**
     * Descuenta lotes, persiste detalle_venta_lotes, actualiza inventario y kardex.
     *
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     */
    public static function aplicarSalida(
        array $asignaciones,
        Detalle $detalle,
        Venta $venta,
        Inventario $inventario,
        float $precio
    ): void {
        if (empty($asignaciones)) {
            return;
        }

        DetalleVentaLote::where('id_detalle_venta', $detalle->id)->delete();

        $cantidadTotal = 0.0;
        foreach ($asignaciones as $asig) {
            $cantidad = (float) $asig['cantidad'];
            $lote = $asig['lote'];
            $lote->stock = max(0, (float) $lote->stock - $cantidad);
            $lote->save();

            DetalleVentaLote::create([
                'id_detalle_venta' => $detalle->id,
                'lote_id' => $asig['lote_id'],
                'cantidad' => $cantidad,
            ]);

            $inventario->kardex($venta, $cantidad, $precio, null, null, [
                'lote_id' => $asig['lote_id'],
            ]);

            $cantidadTotal += $cantidad;
        }

        $inventario->stock = max(0, (float) $inventario->stock - $cantidadTotal);
        $inventario->save();

        $detalle->lote_id = count($asignaciones) === 1 ? $asignaciones[0]['lote_id'] : null;
        $detalle->descripcion = self::formatearDescripcionConLotes($detalle->descripcion, $asignaciones);
        $detalle->save();
    }

    /**
     * Salida por lotes sin detalle de venta (p. ej. composiciones).
     *
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     */
    public static function aplicarSalidaSinDetalle(
        array $asignaciones,
        Venta $venta,
        Inventario $inventario,
        ?float $precio = null
    ): void {
        if (empty($asignaciones)) {
            return;
        }

        $cantidadTotal = 0.0;
        foreach ($asignaciones as $asig) {
            $cantidad = (float) $asig['cantidad'];
            $lote = $asig['lote'];
            $lote->stock = max(0, (float) $lote->stock - $cantidad);
            $lote->save();

            $inventario->kardex($venta, $cantidad, $precio, null, null, [
                'lote_id' => $asig['lote_id'],
            ]);

            $cantidadTotal += $cantidad;
        }

        $inventario->stock = max(0, (float) $inventario->stock - $cantidadTotal);
        $inventario->save();
    }

    /**
     * Devuelve stock a lotes al anular una venta.
     */
    public static function revertirEntrada(Detalle $detalle, Venta $venta, Inventario $inventario, float $cantidadBase): void
    {
        $registros = DetalleVentaLote::where('id_detalle_venta', $detalle->id)->get();

        if ($registros->isNotEmpty()) {
            foreach ($registros as $registro) {
                $lote = Lote::find($registro->lote_id);
                if ($lote) {
                    $lote->stock = (float) $lote->stock + (float) $registro->cantidad;
                    $lote->save();
                }

                $inventario->kardex($venta, (float) $registro->cantidad * -1, null, null, null, [
                    'lote_id' => $registro->lote_id,
                ]);
            }
        } elseif ($detalle->lote_id) {
            $lote = Lote::find($detalle->lote_id);
            if ($lote) {
                $lote->stock = (float) $lote->stock + $cantidadBase;
                $lote->save();
            }

            $inventario->kardex($venta, $cantidadBase * -1, null, null, null, [
                'lote_id' => $detalle->lote_id,
            ]);
        } else {
            $inventario->kardex($venta, $cantidadBase * -1);
        }

        $inventario->stock = (float) $inventario->stock + $cantidadBase;
        $inventario->save();
    }

    /**
     * Vuelve a descontar lotes al cancelar la anulación de una venta.
     */
    public static function reactivarSalidaDesdeDetalle(
        Detalle $detalle,
        Venta $venta,
        Inventario $inventario,
        float $cantidadBase,
        float $precio = 0
    ): void {
        $registros = DetalleVentaLote::where('id_detalle_venta', $detalle->id)->get();

        if ($registros->isNotEmpty()) {
            foreach ($registros as $registro) {
                $lote = Lote::find($registro->lote_id);
                if ($lote) {
                    $lote->stock = max(0, (float) $lote->stock - (float) $registro->cantidad);
                    $lote->save();
                }

                $inventario->kardex($venta, (float) $registro->cantidad, $precio, null, null, [
                    'lote_id' => $registro->lote_id,
                ]);
            }

            $inventario->stock = max(0, (float) $inventario->stock - $cantidadBase);
            $inventario->save();
            return;
        }

        if ($detalle->lote_id) {
            $lote = Lote::find($detalle->lote_id);
            if ($lote) {
                $lote->stock = max(0, (float) $lote->stock - $cantidadBase);
                $lote->save();
            }

            $inventario->kardex($venta, $cantidadBase, $precio, null, null, [
                'lote_id' => $detalle->lote_id,
            ]);
        } else {
            $inventario->kardex($venta, $cantidadBase, $precio);
        }

        $inventario->stock = max(0, (float) $inventario->stock - $cantidadBase);
        $inventario->save();
    }

    /**
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     */
    public static function formatearDescripcionConLotes(?string $descripcion, array $asignaciones): string
    {
        $base = trim((string) $descripcion);
        $base = preg_replace('/\s*\nLotes:.*$/s', '', $base) ?? $base;

        $partes = [];
        foreach ($asignaciones as $asig) {
            $numero = $asig['lote']->numero_lote ?: 'S/N';
            $partes[] = $numero . ' (' . self::formatearCantidad($asig['cantidad']) . ' u)';
        }

        if (empty($partes)) {
            return $base;
        }

        return $base . "\nLotes: " . implode(', ', $partes);
    }

    /**
     * @param  array<int, array{lote_id:int, cantidad:float|int|string}>  $asignacionManual
     * @return array<int, array{lote_id:int, cantidad:float, lote:Lote}>
     */
    private static function validarAsignacionManual(
        array $asignacionManual,
        int $idProducto,
        int $idBodega,
        float $cantidadBase
    ): array {
        $asignaciones = [];
        $total = 0.0;

        foreach ($asignacionManual as $item) {
            if (empty($item['lote_id']) || empty($item['cantidad'])) {
                continue;
            }

            $cantidad = (float) $item['cantidad'];
            if ($cantidad <= 0) {
                continue;
            }

            $lote = Lote::where('id', (int) $item['lote_id'])
                ->where('id_producto', $idProducto)
                ->where('id_bodega', $idBodega)
                ->first();

            if (!$lote) {
                throw new RuntimeException('El lote seleccionado no corresponde al producto o bodega.');
            }

            if ((float) $lote->stock < $cantidad) {
                $numero = $lote->numero_lote ?: 'S/N';
                throw new RuntimeException(
                    "Stock insuficiente en el lote {$numero}. Disponible: {$lote->stock}, requerido: {$cantidad}"
                );
            }

            $asignaciones[] = [
                'lote_id' => (int) $lote->id,
                'cantidad' => $cantidad,
                'lote' => $lote,
            ];
            $total += $cantidad;
        }

        if (abs($total - $cantidadBase) > 0.0001) {
            throw new RuntimeException(
                'La suma de cantidades por lote (' . self::formatearCantidad($total)
                . ') no coincide con la cantidad del detalle (' . self::formatearCantidad($cantidadBase) . ').'
            );
        }

        return $asignaciones;
    }

    /**
     * @return array<int, array{lote_id:int, cantidad:float, lote:Lote}>
     */
    private static function distribuirDesdeLoteUnico(
        int $loteId,
        int $idProducto,
        int $idBodega,
        float $cantidadBase
    ): array {
        $lote = Lote::where('id', $loteId)
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->first();

        if (!$lote) {
            throw new RuntimeException('El lote seleccionado no corresponde al producto o bodega.');
        }

        if ((float) $lote->stock < $cantidadBase) {
            $numero = $lote->numero_lote ?: 'S/N';
            throw new RuntimeException(
                "No hay suficiente stock en el lote {$numero}. Disponible: {$lote->stock}, requerido: {$cantidadBase}"
            );
        }

        return [[
            'lote_id' => (int) $lote->id,
            'cantidad' => $cantidadBase,
            'lote' => $lote,
        ]];
    }

    /**
     * @return array<int, array{lote_id:int, cantidad:float, lote:Lote}>
     */
    private static function distribuirAutomatico(
        int $idProducto,
        int $idBodega,
        float $cantidadBase,
        string $metodologia,
        ?int $lotePreferidoId = null
    ): array {
        $lotes = self::obtenerLotesOrdenados($idProducto, $idBodega, $metodologia);

        if ($lotePreferidoId) {
            $preferido = $lotes->firstWhere('id', $lotePreferidoId);
            if ($preferido) {
                $lotes = collect([$preferido])->merge($lotes->where('id', '!=', $lotePreferidoId));
            }
        }

        $pendiente = $cantidadBase;
        $asignaciones = [];

        foreach ($lotes as $lote) {
            if ($pendiente <= 0) {
                break;
            }

            $stockLote = (float) $lote->stock;
            if ($stockLote <= 0) {
                continue;
            }

            $tomar = min($stockLote, $pendiente);
            $asignaciones[] = [
                'lote_id' => (int) $lote->id,
                'cantidad' => $tomar,
                'lote' => $lote,
            ];
            $pendiente -= $tomar;
        }

        if ($pendiente > 0.0001) {
            throw new RuntimeException(
                'No hay lotes con stock suficiente. Faltan ' . self::formatearCantidad($pendiente) . ' unidad(es).'
            );
        }

        return $asignaciones;
    }

    private static function obtenerLotesOrdenados(int $idProducto, int $idBodega, string $metodologia)
    {
        $query = Lote::where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->where('stock', '>', 0);

        switch ($metodologia) {
            case 'LIFO':
                $query->orderBy('created_at', 'desc');
                break;
            case 'FEFO':
                $query->orderByRaw('fecha_vencimiento IS NULL')
                    ->orderBy('fecha_vencimiento', 'asc')
                    ->orderBy('created_at', 'asc');
                break;
            case 'FIFO':
            default:
                $query->orderBy('created_at', 'asc');
                break;
        }

        return $query->get();
    }

    private static function formatearCantidad(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Resuelve asignaciones de lotes para cualquier documento de salida.
     *
     * @param  array<int, array{lote_id:int, cantidad:float|int|string}>|null  $asignacionManual
     * @return array<int, array{lote_id:int, cantidad:float, lote:Lote}>
     */
    public static function resolverAsignacionesSalida(
        Producto $producto,
        int $idBodega,
        float $cantidadBase,
        string $metodologia,
        bool $lotesActivo,
        ?int $lotePreferidoId = null,
        ?array $asignacionManual = null
    ): array {
        if (!$producto->inventario_por_lotes || !$lotesActivo || $cantidadBase <= 0) {
            return [];
        }

        return self::distribuir(
            (int) $producto->id,
            $idBodega,
            $cantidadBase,
            $metodologia,
            $lotePreferidoId,
            $asignacionManual
        );
    }

    /**
     * Descuenta lotes e inventario para documentos genéricos (salidas, pedidos, etc.).
     *
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     */
    public static function aplicarSalidaDocumento(
        array $asignaciones,
        $documento,
        Inventario $inventario,
        ?float $precio = null,
        array $kardexOpts = []
    ): float {
        if (empty($asignaciones)) {
            return 0.0;
        }

        $cantidadTotal = 0.0;
        foreach ($asignaciones as $asig) {
            $cantidad = (float) $asig['cantidad'];
            $lote = $asig['lote'];
            $lote->stock = max(0, (float) $lote->stock - $cantidad);
            $lote->save();

            $inventario->kardex($documento, $cantidad, $precio, null, null, array_merge($kardexOpts, [
                'lote_id' => $asig['lote_id'],
            ]));

            $cantidadTotal += $cantidad;
        }

        $inventario->stock = max(0, (float) $inventario->stock - $cantidadTotal);
        $inventario->save();

        return $cantidadTotal;
    }

    /**
     * Restaura lotes e inventario para documentos genéricos.
     *
     * @param  iterable<int, object{lote_id:int, cantidad:float|string}>  $registros
     */
    public static function revertirSalidaDocumento(
        iterable $registros,
        $documento,
        Inventario $inventario,
        float $cantidadBase,
        ?int $loteIdLegacy = null,
        ?float $precio = null,
        array $kardexOpts = []
    ): void {
        $revertido = false;

        foreach ($registros as $registro) {
            $lote = Lote::find($registro->lote_id);
            if ($lote) {
                $lote->stock = (float) $lote->stock + (float) $registro->cantidad;
                $lote->save();
            }

            $inventario->kardex($documento, (float) $registro->cantidad * -1, $precio, null, null, array_merge($kardexOpts, [
                'lote_id' => $registro->lote_id,
            ]));
            $revertido = true;
        }

        if (!$revertido && $loteIdLegacy) {
            $lote = Lote::find($loteIdLegacy);
            if ($lote) {
                $lote->stock = (float) $lote->stock + $cantidadBase;
                $lote->save();
            }

            $inventario->kardex($documento, $cantidadBase * -1, $precio, null, null, array_merge($kardexOpts, [
                'lote_id' => $loteIdLegacy,
            ]));
        } elseif (!$revertido) {
            $inventario->kardex($documento, $cantidadBase * -1, $precio, null, null, $kardexOpts);
        }

        $inventario->stock = (float) $inventario->stock + $cantidadBase;
        $inventario->save();
    }

    /**
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     */
    public static function sincronizarDetalleSalidaLotes(int $idDetalleSalida, array $asignaciones): void
    {
        DetalleSalidaLote::where('id_detalle_salida', $idDetalleSalida)->delete();

        foreach ($asignaciones as $asig) {
            DetalleSalidaLote::create([
                'id_detalle_salida' => $idDetalleSalida,
                'lote_id' => $asig['lote_id'],
                'cantidad' => $asig['cantidad'],
            ]);
        }
    }

    /**
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     */
    public static function sincronizarPedidoDetalleLotes(int $pedidoDetalleId, array $asignaciones): void
    {
        PedidoDetalleLote::where('pedido_detalle_id', $pedidoDetalleId)->delete();

        foreach ($asignaciones as $asig) {
            PedidoDetalleLote::create([
                'pedido_detalle_id' => $pedidoDetalleId,
                'lote_id' => $asig['lote_id'],
                'cantidad' => $asig['cantidad'],
            ]);
        }
    }

    /**
     * Traslada stock entre bodegas respetando múltiples lotes de origen.
     *
     * @param  array<int, array{lote_id:int, cantidad:float, lote:Lote}>  $asignaciones
     * @return array<int, array{lote_id:int, lote_id_destino:int|null, cantidad:float}>
     */
    public static function aplicarTrasladoLotes(
        array $asignaciones,
        Producto $producto,
        int $idBodegaOrigen,
        int $idBodegaDestino,
        Traslado $traslado,
        Inventario $origen,
        Inventario $destino,
        ?int $loteIdDestinoPreferido = null
    ): array {
        $pivotRows = [];

        foreach ($asignaciones as $asig) {
            $cantidad = (float) $asig['cantidad'];
            $loteOrigen = $asig['lote'];
            $loteOrigen->refresh();

            if ($loteOrigen->id_bodega != $idBodegaOrigen) {
                throw new RuntimeException('El lote seleccionado no pertenece a la bodega de origen.');
            }

            if ((float) $loteOrigen->stock < $cantidad) {
                $numero = $loteOrigen->numero_lote ?: 'S/N';
                throw new RuntimeException(
                    "Stock insuficiente en el lote {$numero}. Disponible: {$loteOrigen->stock}, requerido: {$cantidad}"
                );
            }

            $loteOrigen->stock = max(0, (float) $loteOrigen->stock - $cantidad);
            $loteOrigen->save();

            $loteDestino = self::resolverLoteDestinoTraslado(
                $producto,
                $idBodegaDestino,
                $loteOrigen,
                $cantidad,
                $loteIdDestinoPreferido
            );

            $origen->kardex($traslado, $cantidad * -1, null, null, null, ['lote_id' => $loteOrigen->id]);
            $destino->kardex($traslado, $cantidad, null, null, null, ['lote_id' => $loteDestino->id]);

            $pivotRows[] = [
                'lote_id' => (int) $loteOrigen->id,
                'lote_id_destino' => $loteDestino->id,
                'cantidad' => $cantidad,
            ];
        }

        $cantidadTotal = array_sum(array_column($pivotRows, 'cantidad'));
        $origen->stock = max(0, (float) $origen->stock - $cantidadTotal);
        $origen->save();
        $destino->stock = (float) $destino->stock + $cantidadTotal;
        $destino->save();

        return $pivotRows;
    }

    /**
     * @param  iterable<int, TrasladoLote|object{lote_id:int, lote_id_destino:int|null, cantidad:float|string}>  $registros
     */
    public static function revertirTrasladoLotes(
        iterable $registros,
        Producto $producto,
        Traslado $traslado,
        Inventario $origen,
        Inventario $destino,
        float $cantidadBase,
        ?int $loteIdLegacy = null,
        ?int $loteIdDestinoLegacy = null
    ): void {
        $revertido = false;

        foreach ($registros as $registro) {
            $cantidad = (float) $registro->cantidad;
            $loteOrigen = Lote::find($registro->lote_id);
            if ($loteOrigen) {
                $loteOrigen->stock += $cantidad;
                $loteOrigen->save();
            }

            $loteDestino = null;
            if (!empty($registro->lote_id_destino)) {
                $loteDestino = Lote::find($registro->lote_id_destino);
            } elseif ($loteOrigen) {
                $loteDestino = Lote::where('id_producto', $producto->id)
                    ->where('id_bodega', $traslado->id_bodega)
                    ->where('numero_lote', $loteOrigen->numero_lote)
                    ->first();
            }

            if ($loteDestino) {
                $loteDestino->stock = max(0, (float) $loteDestino->stock - $cantidad);
                $loteDestino->save();
            }

            $origen->kardex($traslado, $cantidad, null, null, null, ['lote_id' => $registro->lote_id]);
            $destino->kardex($traslado, $cantidad * -1, null, null, null, [
                'lote_id' => $loteDestino ? $loteDestino->id : null,
            ]);
            $revertido = true;
        }

        if (!$revertido && $loteIdLegacy) {
            $loteOrigen = Lote::find($loteIdLegacy);
            if ($loteOrigen) {
                $loteOrigen->stock += $cantidadBase;
                $loteOrigen->save();
            }

            if ($loteIdDestinoLegacy) {
                $loteDestino = Lote::find($loteIdDestinoLegacy);
            } elseif ($loteOrigen) {
                $loteDestino = Lote::where('id_producto', $producto->id)
                    ->where('id_bodega', $traslado->id_bodega)
                    ->where('numero_lote', $loteOrigen->numero_lote)
                    ->first();
            } else {
                $loteDestino = null;
            }

            if ($loteDestino) {
                $loteDestino->stock = max(0, (float) $loteDestino->stock - $cantidadBase);
                $loteDestino->save();
            }

            $origen->kardex($traslado, $cantidadBase, null, null, null, ['lote_id' => $loteIdLegacy]);
            $destino->kardex($traslado, $cantidadBase * -1, null, null, null, [
                'lote_id' => $loteDestino ? $loteDestino->id : null,
            ]);
        }

        $origen->stock += $cantidadBase;
        $origen->save();
        $destino->stock = max(0, (float) $destino->stock - $cantidadBase);
        $destino->save();
    }

    /**
     * @param  array<int, array{lote_id:int, lote_id_destino:int|null, cantidad:float}>  $pivotRows
     */
    public static function sincronizarTrasladoLotes(int $trasladoId, array $pivotRows): void
    {
        TrasladoLote::where('traslado_id', $trasladoId)->delete();

        foreach ($pivotRows as $row) {
            TrasladoLote::create([
                'traslado_id' => $trasladoId,
                'lote_id' => $row['lote_id'],
                'lote_id_destino' => $row['lote_id_destino'] ?? null,
                'cantidad' => $row['cantidad'],
            ]);
        }
    }

    private static function resolverLoteDestinoTraslado(
        Producto $producto,
        int $idBodegaDestino,
        Lote $loteOrigen,
        float $cantidad,
        ?int $loteIdDestinoPreferido = null
    ): Lote {
        if ($loteIdDestinoPreferido) {
            $loteDestino = Lote::findOrFail($loteIdDestinoPreferido);

            if ($loteDestino->id_bodega != $idBodegaDestino) {
                throw new RuntimeException('El lote de destino no pertenece a la bodega de destino.');
            }

            if ($loteDestino->id_producto != $producto->id) {
                throw new RuntimeException('El lote de destino no corresponde al producto.');
            }

            $loteDestino->stock += $cantidad;
            $loteDestino->save();

            return $loteDestino;
        }

        $loteDestino = Lote::where('id_producto', $producto->id)
            ->where('id_bodega', $idBodegaDestino)
            ->where('numero_lote', $loteOrigen->numero_lote)
            ->first();

        if ($loteDestino) {
            $loteDestino->stock += $cantidad;
            $loteDestino->save();

            return $loteDestino;
        }

        return Lote::create([
            'id_producto' => $producto->id,
            'id_bodega' => $idBodegaDestino,
            'numero_lote' => $loteOrigen->numero_lote,
            'fecha_vencimiento' => $loteOrigen->fecha_vencimiento,
            'fecha_fabricacion' => $loteOrigen->fecha_fabricacion,
            'stock' => $cantidad,
            'stock_inicial' => $cantidad,
            'id_empresa' => Auth::user()->id_empresa ?? $producto->id_empresa,
        ]);
    }
}
