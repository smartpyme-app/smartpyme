<?php

namespace App\Exports\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Líneas de venta/compra del período unidas con devoluciones (fecha de la devolución, montos negativos).
 * Al agrupar, cantidades y totales quedan netos.
 */
class DetallesConDevolucionesQuery
{
    public static function lineasVenta(Request $request, string $fechaInicio, string $fechaFin): Builder
    {
        $idEmpresa = (int) $request->id_empresa;
        $sucursales = self::sucursales($request);

        $ventas = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->where('v.id_empresa', $idEmpresa)
            ->where('v.estado', '!=', 'Anulada')
            ->where('v.cotizacion', 0)
            ->whereBetween('v.fecha', [$fechaInicio, $fechaFin])
            ->when($sucursales, function ($query) use ($sucursales) {
                return $query->whereIn('v.id_sucursal', $sucursales);
            })
            ->select([
                'dv.id_producto',
                'v.fecha',
                'v.id_sucursal',
                DB::raw('dv.cantidad as cantidad'),
                DB::raw('dv.total as total'),
                DB::raw('COALESCE(dv.total_costo, dv.cantidad * dv.costo, 0) as total_costo'),
            ]);

        $devoluciones = DB::table('detalles_devolucion_venta as ddv')
            ->join('devoluciones_venta as d', 'd.id', '=', 'ddv.id_devolucion_venta')
            ->where('d.id_empresa', $idEmpresa)
            ->where('d.enable', 1)
            ->whereBetween('d.fecha', [$fechaInicio, $fechaFin])
            ->when($sucursales, function ($query) use ($sucursales) {
                return $query->whereIn('d.id_sucursal', $sucursales);
            })
            ->select([
                'ddv.id_producto',
                'd.fecha',
                'd.id_sucursal',
                DB::raw('-ABS(ddv.cantidad) as cantidad'),
                DB::raw('-ABS(ddv.total) as total'),
                DB::raw('-ABS(COALESCE(ddv.cantidad * ddv.costo, 0)) as total_costo'),
            ]);

        return $ventas->unionAll($devoluciones);
    }

    public static function lineasCompra(Request $request, string $fechaInicio, string $fechaFin): Builder
    {
        $idEmpresa = (int) $request->id_empresa;
        $sucursales = self::sucursales($request);

        $compras = DB::table('detalles_compra as dc')
            ->join('compras as c', 'c.id', '=', 'dc.id_compra')
            ->where('c.id_empresa', $idEmpresa)
            ->where('c.cotizacion', 0)
            ->whereBetween('c.fecha', [$fechaInicio, $fechaFin])
            ->when($sucursales, function ($query) use ($sucursales) {
                return $query->whereIn('c.id_sucursal', $sucursales);
            })
            ->select([
                'dc.id_producto',
                'c.fecha',
                'c.id_sucursal',
                DB::raw('dc.cantidad as cantidad'),
                DB::raw('dc.total as total'),
                DB::raw('COALESCE(dc.cantidad * dc.costo, 0) as total_costo'),
                DB::raw('dc.costo as costo'),
            ]);

        $devoluciones = DB::table('detalles_devolucion_compra as ddc')
            ->join('devoluciones_compra as d', 'd.id', '=', 'ddc.id_devolucion_compra')
            ->where('d.id_empresa', $idEmpresa)
            ->where('d.enable', 1)
            ->whereBetween('d.fecha', [$fechaInicio, $fechaFin])
            ->when($sucursales, function ($query) use ($sucursales) {
                return $query->whereIn('d.id_sucursal', $sucursales);
            })
            ->select([
                'ddc.id_producto',
                'd.fecha',
                'd.id_sucursal',
                DB::raw('-ABS(ddc.cantidad) as cantidad'),
                DB::raw('-ABS(COALESCE(ddc.total, ddc.subtotal, ddc.cantidad * ddc.costo, 0)) as total'),
                DB::raw('-ABS(COALESCE(ddc.cantidad * ddc.costo, 0)) as total_costo'),
                DB::raw('ddc.costo as costo'),
            ]);

        return $compras->unionAll($devoluciones);
    }

    public static function sucursalesVenta(Request $request, string $fechaInicio, string $fechaFin)
    {
        $lineas = self::lineasVenta($request, $fechaInicio, $fechaFin);

        return DB::query()
            ->fromSub($lineas, 'lineas')
            ->join('sucursales', 'sucursales.id', '=', 'lineas.id_sucursal')
            ->join('productos', 'productos.id', '=', 'lineas.id_producto')
            ->when($request->categorias, function ($q) use ($request) {
                return $q->whereIn('productos.id_categoria', $request->categorias);
            })
            ->when($request->marcas, function ($q) use ($request) {
                return $q->whereIn('productos.marca', $request->marcas);
            })
            ->select('sucursales.id', 'sucursales.nombre')
            ->distinct()
            ->orderBy('sucursales.nombre')
            ->get();
    }

    /**
     * @return array<int, int>|null
     */
    private static function sucursales(Request $request): ?array
    {
        if (!empty($request->sucursales) && is_array($request->sucursales)) {
            return array_map('intval', $request->sucursales);
        }

        return null;
    }
}
