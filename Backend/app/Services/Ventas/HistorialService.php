<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HistorialService
{
    /**
     * Obtener historial de ventas
     *
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return Collection
     */
    public function obtenerHistorial(string $fechaInicio, string $fechaFin): Collection
    {
        $ventas = Venta::where('estado', 'Pagada')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->fecha)->format('d-m-Y');
            });

        $movimientos = collect();

        foreach ($ventas as $venta) {
            $ventaTotal = $venta->sum('total');
            $costoTotal = $venta->sum('subcosto');
            $movimientos->push([
                'cantidad' => $venta->count(),
                'fecha' => $venta[0]->fecha,
                'total' => $ventaTotal,
                'costo' => $costoTotal,
                'utilidad' => $ventaTotal - $costoTotal,
                'detalles' => $venta
            ]);
        }

        return $movimientos;
    }

    /**
     * Obtener ventas sin devolución
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerVentasSinDevolucion()
    {
        $fechaInicio = Carbon::now()->subMonths(2)->startOfMonth();
        $fechaFin = Carbon::now()->endOfMonth();

        return Venta::where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->whereHas('documento', function ($q) {
                $q->whereIn('nombre', ['Factura', 'Crédito fiscal']);
            })
            ->whereDoesntHave('devoluciones')
            ->orderBy('fecha', 'DESC')
            ->get();
    }
}


