<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use Exception;

class DevolucionConsignaVentaService
{
    /**
     * Reduce líneas y totales de la venta en estado Consigna tras devolver mercancía.
     *
     * @param  array<int, array{id?: int, id_producto: int, cantidad: float|int|string}>  $detallesDevolucion
     */
    public function reducirRemanenteDesdeDevolucion(Venta $venta, array $detallesDevolucion): void
    {
        if ($venta->estado !== 'Consigna') {
            return;
        }

        $venta->loadMissing('detalles');
        $subTotalAnterior = (float) $venta->sub_total;
        $ivaAnterior = (float) $venta->iva;

        foreach ($detallesDevolucion as $det) {
            $cantidadDev = (float) ($det['cantidad'] ?? 0);
            if ($cantidadDev <= 0) {
                continue;
            }

            $detalleVenta = null;
            if (! empty($det['id'])) {
                $detalleVenta = $venta->detalles->firstWhere('id', (int) $det['id']);
            }
            if (! $detalleVenta && ! empty($det['id_producto'])) {
                $detalleVenta = $venta->detalles->firstWhere('id_producto', (int) $det['id_producto']);
            }

            if (! $detalleVenta) {
                throw new Exception('Producto no encontrado en la remisión en consigna.');
            }

            if ($cantidadDev > (float) $detalleVenta->cantidad + 0.0001) {
                throw new Exception(
                    'Cantidad a devolver supera el saldo en consigna del producto #' . $detalleVenta->id_producto . '.'
                );
            }

            $nuevaCantidad = (float) $detalleVenta->cantidad - $cantidadDev;
            if ($nuevaCantidad <= 0.0001) {
                $detalleVenta->delete();
            } else {
                $costoUnit = (float) ($detalleVenta->costo ?? 0);
                $detalleVenta->cantidad = $nuevaCantidad;
                $detalleVenta->total = $nuevaCantidad * (float) $detalleVenta->precio;
                $detalleVenta->total_costo = $nuevaCantidad * $costoUnit;
                $detalleVenta->save();
            }
        }

        $venta->refresh();
        $venta->load('detalles');

        $subTotal = round((float) $venta->detalles->sum('total'), 2);
        $totalCosto = round((float) $venta->detalles->sum('total_costo'), 2);
        $venta->sub_total = $subTotal;
        $venta->total_costo = $totalCosto;
        // ponytail: proporcional simple de IVA según subtotal restante vs original
        if ($subTotalAnterior > 0 && $subTotal < $subTotalAnterior) {
            $venta->iva = round($ivaAnterior * ($subTotal / $subTotalAnterior), 2);
        } elseif ($subTotal <= 0) {
            $venta->iva = 0;
        }
        $venta->total = round($subTotal + (float) $venta->iva, 2);
        $rate = (float) ($venta->exchange_rate ?: 1);
        $venta->equivalent_total = round($venta->total * $rate, 5);
        $venta->equivalent_iva = round((float) $venta->iva * $rate, 5);
        $venta->save();
    }
}
