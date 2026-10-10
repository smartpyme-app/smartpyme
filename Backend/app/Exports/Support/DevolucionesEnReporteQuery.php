<?php

namespace App\Exports\Support;

use Illuminate\Support\Facades\DB;

class DevolucionesEnReporteQuery
{
    public static function porCategoriaVendedor($idEmpresa, $inicio, $fin, ?array $sucursales, array $categoriasIds)
    {
        $query = DB::table('detalles_devolucion_venta as ddv')
            ->join('devoluciones_venta as d', 'd.id', '=', 'ddv.id_devolucion_venta')
            ->join('ventas as vv', 'vv.id', '=', 'd.id_venta')
            ->join('productos as pro', 'pro.id', '=', 'ddv.id_producto')
            ->join('categorias as cat', 'cat.id', '=', 'pro.id_categoria')
            ->leftJoin('users as us', 'us.id', '=', 'vv.id_vendedor')
            ->where('d.enable', 1)
            ->where('d.id_empresa', $idEmpresa)
            ->where('vv.cotizacion', 0);

        RangoFecha::aplicar($query, 'd.fecha', $inicio, $fin);

        if (!empty($sucursales)) {
            $query->whereIn('d.id_sucursal', $sucursales);
        }
        if (!empty($categoriasIds)) {
            $query->whereIn('cat.id', $categoriasIds);
        }

        return $query->select(
            'cat.id as id_categoria',
            'cat.nombre as nombre_categoria',
            DB::raw("COALESCE(us.name, 'Sin vendedor') as nombre_vendedor"),
            DB::raw('COALESCE(us.id, 0) as id_vendedor'),
            DB::raw('SUM(-ABS(ddv.total)) as total_ventas')
        )
            ->groupBy('cat.id', 'cat.nombre', DB::raw("COALESCE(us.name, 'Sin vendedor')"), DB::raw('COALESCE(us.id, 0)'))
            ->get();
    }

    public static function filasDetalleVendedor($idEmpresa, $inicio, $fin, ?array $sucursales)
    {
        $query = DB::table('detalles_devolucion_venta as ddv')
            ->join('devoluciones_venta as d', 'd.id', '=', 'ddv.id_devolucion_venta')
            ->join('ventas as vv', 'vv.id', '=', 'd.id_venta')
            ->leftJoin('users as us', 'us.id', '=', 'vv.id_vendedor')
            ->leftJoin('productos as pro', 'pro.id', '=', 'ddv.id_producto')
            ->leftJoin('categorias as cat', 'cat.id', '=', 'pro.id_categoria')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'd.id_cliente')
            ->leftJoin('sucursales as suc', 'suc.id', '=', 'd.id_sucursal')
            ->leftJoin('documentos as doc', 'doc.id', '=', 'd.id_documento')
            ->where('d.enable', 1)
            ->where('d.id_empresa', $idEmpresa)
            ->where('vv.cotizacion', 0);

        RangoFecha::aplicar($query, 'd.fecha', $inicio, $fin);

        if (!empty($sucursales)) {
            $query->whereIn('d.id_sucursal', $sucursales);
        }

        return $query->select(
            DB::raw("COALESCE(us.name, 'Sin vendedor') as nombre_vendedor"),
            'd.correlativo',
            DB::raw("COALESCE(doc.nombre, 'Devolución') as tipo_documento"),
            'd.fecha',
            DB::raw('DATE_FORMAT(d.created_at, "%H:%i:%s") as hora'),
            'cl.nombre as nombre_cliente',
            'suc.nombre as nombre_sucursal',
            'cat.nombre as nombre_categoria',
            'pro.nombre as nombre_producto',
            DB::raw('-ABS(ddv.cantidad) as cantidad'),
            'ddv.precio',
            DB::raw('-ABS(COALESCE(ddv.descuento, 0)) as descuento'),
            DB::raw('0 as iva'),
            DB::raw('-ABS(COALESCE(ddv.total, 0)) as subtotal'),
            DB::raw('-ABS(COALESCE(ddv.total, 0)) as total_con_descuento')
        )->get();
    }
}
