<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Inventario\Inventario;
use Illuminate\Support\Facades\Log;

class InventarioService
{
    /**
     * Actualizar inventario al crear venta
     *
     * @param Venta $venta
     * @param array $detalles
     * @param bool $esCotizacion
     * @return void
     */
    public function actualizarInventarioVenta(Venta $venta, array $detalles, bool $esCotizacion = false): void
    {
        if ($esCotizacion) {
            return;
        }

        foreach ($detalles as $det) {
            $this->actualizarStockProducto($venta, $det);
            $this->actualizarStockComposiciones($venta, $det);
        }
    }

    /**
     * Actualizar stock de un producto
     *
     * @param Venta $venta
     * @param array $detalle
     * @return void
     */
    protected function actualizarStockProducto(Venta $venta, array $detalle): void
    {
        $inventario = Inventario::where('id_producto', $detalle['id_producto'])
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock -= $detalle['cantidad'];
            $inventario->save();
            $inventario->kardex($venta, $detalle['cantidad'], $detalle['precio']);
        }
    }

    /**
     * Actualizar stock de composiciones
     *
     * @param Venta $venta
     * @param array $detalle
     * @return void
     */
    protected function actualizarStockComposiciones(Venta $venta, array $detalle): void
    {
        if (!isset($detalle['composiciones'])) {
            return;
        }

        foreach ($detalle['composiciones'] as $comp) {
            $inventario = Inventario::where('id_producto', $comp['id_compuesto'])
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($inventario) {
                $cantidadTotal = $detalle['cantidad'] * $comp['cantidad'];
                $inventario->stock -= $cantidadTotal;
                $inventario->save();
                $inventario->kardex($venta, $cantidadTotal);
            }
        }
    }

    /**
     * Revertir inventario al anular venta
     *
     * @param Venta $venta
     * @return void
     */
    public function revertirInventarioAnulacion(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $this->revertirStockProducto($venta, $detalle);
            $this->revertirStockComposiciones($venta, $detalle);
        }
    }

    /**
     * Revertir stock de un producto
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function revertirStockProducto(Venta $venta, Detalle $detalle): void
    {
        $inventario = Inventario::where('id_producto', $detalle->id_producto)
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock += $detalle->cantidad;
            $inventario->save();
            $inventario->kardex($venta, $detalle->cantidad * -1);
        }
    }

    /**
     * Revertir stock de composiciones
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function revertirStockComposiciones(Venta $venta, Detalle $detalle): void
    {
        foreach ($detalle->composiciones()->get() as $comp) {
            $inventario = Inventario::where('id_producto', $comp->id_producto)
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($inventario) {
                $cantidadTotal = $detalle->cantidad * $comp->cantidad;
                $inventario->stock += $cantidadTotal;
                $inventario->save();
                $inventario->kardex($venta, $cantidadTotal * -1);
            }
        }
    }

    /**
     * Aplicar inventario al cancelar anulación
     *
     * @param Venta $venta
     * @return void
     */
    public function aplicarInventarioCancelacionAnulacion(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $this->aplicarStockProducto($venta, $detalle);
            $this->aplicarStockComposiciones($venta, $detalle);
        }
    }

    /**
     * Aplicar stock de un producto
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function aplicarStockProducto(Venta $venta, Detalle $detalle): void
    {
        $inventario = Inventario::where('id_producto', $detalle->id_producto)
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock -= $detalle->cantidad;
            $inventario->save();
            $inventario->kardex($venta, $detalle->cantidad);
        }
    }

    /**
     * Aplicar stock de composiciones
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function aplicarStockComposiciones(Venta $venta, Detalle $detalle): void
    {
        foreach ($detalle->composiciones()->get() as $comp) {
            $inventario = Inventario::where('id_producto', $comp->id_producto)
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($inventario) {
                $cantidadTotal = $detalle->cantidad * $comp->cantidad;
                $inventario->stock -= $cantidadTotal;
                $inventario->save();
                $inventario->kardex($venta, $cantidadTotal);
            }
        }
    }
}


