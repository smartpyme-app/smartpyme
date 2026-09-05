<?php

namespace App\Services\Admin;

use Illuminate\Support\Collection;

class ResumenCajaCalculator
{
    public static function totalesPorForma(
        string $nombre,
        Collection $ventasPagadas,
        Collection $abonos,
        Collection $detallesMetodosDePago,
        Collection $devolucionesVentas
    ): array {
        $idsVentasConAbonoDelPeriodo = $abonos->pluck('id_venta')->unique()->filter();

        $ventasPagadasSinAbono = $ventasPagadas
            ->where('forma_pago', $nombre)
            ->reject(fn ($venta) => $idsVentasConAbonoDelPeriodo->contains($venta->id ?? null));

        $cantidad = $ventasPagadasSinAbono->count()
            + $detallesMetodosDePago->where('nombre', $nombre)->count()
            + $abonos->where('forma_pago', $nombre)->count()
            - $devolucionesVentas->where('forma_pago', $nombre)->count();

        $total = $ventasPagadasSinAbono->sum('total')
            + $detallesMetodosDePago->where('nombre', $nombre)->sum('total')
            + $abonos->where('forma_pago', $nombre)->sum('total')
            - $devolucionesVentas->where('forma_pago', $nombre)->sum('total');

        return [
            'cantidad' => $cantidad,
            'total' => $total,
        ];
    }
}
