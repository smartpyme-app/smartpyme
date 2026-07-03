<?php

namespace App\Services\Inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleVentaLote;
use App\Models\Ventas\Venta;
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
}
