<?php

namespace App\Http\Controllers;

use App\Exports\ReportesAutomaticos\InventarioPorSucursal\InventarioExport;
use App\Models\Admin\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class InventarioController extends Controller
{
    public function exportarReporteProgramado($configuracion, $fecha_inicio, $fecha_fin)
    {
        try {
            $sucursales = $configuracion->sucursales ?? [];

            if (empty($sucursales)) {
                $sucursales = Sucursal::where('id_empresa', Auth::user()->id_empresa)
                    ->pluck('id')
                    ->toArray();
            }

            $bodegas = $this->getBodegasPorSucursales($sucursales);

            $query = $this->buildInventarioQuery($bodegas, $fecha_inicio, $fecha_fin);

            $inventarioData = $query->get();

            $datosParaExportar = $this->prepararDatosInventario($inventarioData, $sucursales);

            $nombreArchivo = $this->generarNombreArchivo($configuracion, $fecha_inicio, $fecha_fin, $sucursales);

            return Excel::download(
                new InventarioExport($datosParaExportar, $configuracion, $fecha_inicio, $fecha_fin),
                $nombreArchivo . '.xlsx',
                \Maatwebsite\Excel\Excel::XLSX
            );
        } catch (\Exception $e) {
            Log::error('Error en exportarReporteInventario: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function buildInventarioQuery($bodegas, $fecha_inicio, $fecha_fin)
    {
        return DB::table('productos as p')
            ->join('inventario as i', 'p.id', '=', 'i.id_producto')
            ->join('sucursal_bodegas as b', 'i.id_bodega', '=', 'b.id')
            ->join('sucursales as s', 'b.id_sucursal', '=', 's.id')
            ->leftJoin('categorias as c', 'p.id_categoria', '=', 'c.id')
            ->leftJoin('producto_proveedores as pp', 'p.id', '=', 'pp.id_producto')
            ->leftJoin('proveedores as prov', 'pp.id_proveedor', '=', 'prov.id')
            
            ->leftJoin(DB::raw('(
                SELECT 
                    k.id_producto,
                    k.id_inventario,
                    k.precio_unitario,
                    k.costo_unitario,
                    k.fecha,
                    k.detalle,
                    ROW_NUMBER() OVER (
                        PARTITION BY k.id_producto, k.id_inventario 
                        ORDER BY k.fecha DESC, k.id DESC
                    ) as rn
                FROM kardexs k
                WHERE k.precio_unitario > 0 OR k.costo_unitario > 0
            ) as k_reciente'), function($join) {
                $join->on('p.id', '=', 'k_reciente.id_producto')
                     ->on('b.id', '=', 'k_reciente.id_inventario')
                     ->where('k_reciente.rn', '=', 1);
            })
            
            ->select([
                'p.nombre as nombre_producto',
                'c.nombre as nombre_categoria', 
                'p.codigo as codigo_producto',
                DB::raw('COALESCE(k_reciente.precio_unitario, p.precio, 0) as precio_unitario'),
                DB::raw('COALESCE(k_reciente.costo_unitario, p.costo_promedio, p.costo, 0) as costo_unitario'),
                'b.nombre as nombre_bodega',
                's.nombre as nombre_sucursal',
                'i.stock as cantidad_actual',
                'i.updated_at as fecha_ultima_actualizacion',
                DB::raw('CASE 
                    WHEN prov.tipo = "Persona" THEN CONCAT(prov.nombre, " ", prov.apellido)
                    WHEN prov.tipo = "Empresa" THEN prov.nombre_empresa
                    ELSE "Sin proveedor"
                END as nombre_proveedor'),
                DB::raw('(i.stock * COALESCE(k_reciente.costo_unitario, p.costo_promedio, p.costo, 0)) as valor_inventario'),
                DB::raw('(i.stock * COALESCE(k_reciente.precio_unitario, p.precio, 0)) as precio_total'),
                DB::raw('(i.stock * COALESCE(k_reciente.costo_unitario, p.costo_promedio, p.costo, 0)) as costo_total'),
                DB::raw('CASE 
                    WHEN i.stock <= i.stock_minimo THEN "Bajo" 
                    WHEN i.stock >= i.stock_maximo THEN "Alto" 
                    ELSE "Normal" 
                END as estado_stock')
            ])
            ->whereIn('b.id', $bodegas)
            ->where('s.id_empresa', Auth::user()->id_empresa)
            ->where('i.updated_at', '<=', $fecha_fin . ' 23:59:59') 
            ->whereNull('i.deleted_at')
            ->whereNull('p.deleted_at')
            ->orderBy('s.nombre')
            ->orderBy('b.nombre') 
            ->orderBy('c.nombre')
            ->orderBy('p.nombre');
    }

    private function getBodegasPorSucursales($sucursales)
    {
        return DB::table('sucursal_bodegas')
            ->whereIn('id_sucursal', $sucursales)
            ->pluck('id')
            ->toArray();
    }

    private function prepararDatosInventario($inventarioData, $sucursales)
    {
        $datosPreparados = [];

        $inventarioPorSucursal = $inventarioData->groupBy('nombre_sucursal');

        foreach ($inventarioPorSucursal as $sucursalNombre => $datosSucursal) {
            $datosPreparados[] = [
                'tipo' => 'header_sucursal',
                'sucursal' => $sucursalNombre,
                'total_productos' => $datosSucursal->count(),
                'total_bodegas' => $datosSucursal->pluck('nombre_bodega')->unique()->count(),
                'valor_total' => $datosSucursal->sum('valor_inventario')
            ];

            $inventarioPorBodega = $datosSucursal->groupBy('nombre_bodega');

            foreach ($inventarioPorBodega as $bodegaNombre => $productos) {
                $datosPreparados[] = [
                    'tipo' => 'header_bodega',
                    'sucursal' => $sucursalNombre,
                    'bodega' => $bodegaNombre,
                    'total_productos' => $productos->count(),
                    'valor_total' => $productos->sum('valor_inventario')
                ];

                foreach ($productos as $producto) {
                    $datosPreparados[] = [
                        'tipo' => 'producto',
                        'sucursal_nombre' => $producto->nombre_sucursal,
                        'bodega_nombre' => $producto->nombre_bodega,
                        'categoria_nombre' => $producto->nombre_categoria ?? 'Sin categoría',
                        'producto_codigo' => $producto->codigo_producto,
                        'producto_nombre' => $producto->nombre_producto,
                        'cantidad_actual' => $producto->cantidad_actual,
                        'precio_unitario' => $producto->precio_unitario,
                        'costo_unitario' => $producto->costo_unitario,
                        'nombre_proveedor' => $producto->nombre_proveedor,
                        'valor_inventario' => $producto->valor_inventario,
                        'precio_total' => $producto->precio_total,
                        'costo_total' => $producto->costo_total,
                        'estado_stock' => $producto->estado_stock,
                        'ultima_actualizacion' => $producto->fecha_ultima_actualizacion
                    ];
                }
            }
        }

        return $datosPreparados;
    }

    private function generarNombreArchivo($configuracion, $fecha_inicio, $fecha_fin, $sucursales)
    {
        $nombreBase = 'Inventario_por_Sucursal';
        // $fechas = $fecha_inicio . '_al_' . $fecha_fin;

        if (count($sucursales) === 1) {
            $sucursal = Sucursal::find($sucursales[0]);
            $sucursalInfo = $sucursal ? '_' . Str::slug($sucursal->nombre) : '';
        } elseif (count($sucursales) <= 3) {
            $nombres = Sucursal::whereIn('id', $sucursales)
                ->pluck('nombre')
                ->map(function ($nombre) {
                    return Str::slug($nombre);
                })
                ->join('-');
            $sucursalInfo = '_' . $nombres;
        } else {
            $sucursalInfo = '_' . count($sucursales) . '_sucursales';
        }

        return $nombreBase . '_'. $sucursalInfo;
    }
}
