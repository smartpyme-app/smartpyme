<?php

namespace App\Services\Reportes;

use App\Exports\CobrosPorVendedorExport;
use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorExport;
use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorPdfExport;
use App\Exports\ReportesAutomaticos\EstadoFinancieroConsolidadoSucursales\EstadoFinancieroConsolidadoSucursalesExport;
use App\Exports\ReportesAutomaticos\InventarioPorSucursal\InventarioExport;
use App\Exports\ReportesAutomaticos\VentasComprasPorMarcaProveedor\VentasComprasPorMarcaProveedorExport;
use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor\VentasPorCategoriaVendedorExport;
use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor\VentasPorCategoriaVendedorPdfExport;
use App\Exports\ReportesAutomaticos\VentasPorVendedor\VentasPorVendedorExport;
use App\Exports\ComprasDetallesExport;
use App\Exports\ComprasExport;
use App\Exports\VentasDesglosadasPorVendedorExport;
use App\Exports\VentasDetallesExport;
use App\Exports\VentasPorUtilidadesExport;
use App\Models\Admin\Empresa;
use App\Models\Admin\ReporteConfiguracion;
use App\Models\Admin\Sucursal;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReporteAutomaticoGenerator
{
    /**
     * Genera el archivo en storage/app/temp/reportes y devuelve [ruta_relativa, nombre_archivo, mime].
     *
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function generate(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin,
        ?array $sucursales,
        string $formato = 'excel'
    ): array {
        if ($sucursales !== null) {
            $configuracion->sucursales = $sucursales;
        }

        $dir = storage_path('app/temp/reportes');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($formato === 'pdf') {
            return $this->generatePdf($configuracion, $fechaInicio, $fechaFin, $dir);
        }

        return $this->generateExcel($configuracion, $fechaInicio, $fechaFin, $dir);
    }

    /**
     * @return array{ruta: string, nombre: string, mime: string}
     */
    private function generateExcel(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin,
        string $dir
    ): array {
        $export = $this->buildExcelExport($configuracion, $fechaInicio, $fechaFin);
        $nombre = $configuracion->tipo_reporte . '-' . $fechaInicio . '-' . $fechaFin . '-' . time() . '.xlsx';
        $rutaRelativa = 'temp/reportes/' . $nombre;
        $absolute = $dir . '/' . $nombre;

        Excel::store($export, $rutaRelativa, 'local');

        if (!file_exists($absolute)) {
            throw new \RuntimeException("El archivo Excel no se generó en: {$absolute}");
        }

        return [
            'ruta' => $rutaRelativa,
            'nombre' => $nombre,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @return array{ruta: string, nombre: string, mime: string}
     */
    private function generatePdf(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin,
        string $dir
    ): array {
        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-categoria-vendedor':
                $exporter = new VentasPorCategoriaVendedorPdfExport(
                    $fechaInicio,
                    $fechaFin,
                    $configuracion->id_empresa,
                    $configuracion,
                    $configuracion->sucursales
                );
                break;
            case 'detalle-ventas-vendedor':
                $exporter = new DetalleVentasVendedorPdfExport(
                    $fechaInicio,
                    $fechaFin,
                    $configuracion->id_empresa,
                    $configuracion,
                    $configuracion->sucursales
                );
                break;
            default:
                throw new \InvalidArgumentException('Formato PDF no disponible para este tipo de reporte');
        }

        $response = $exporter->download();

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $payload = $response->getData(true);
            throw new \RuntimeException($payload['error'] ?? 'Error al generar PDF');
        }

        $contentType = method_exists($response, 'headers')
            ? ($response->headers->get('Content-Type') ?: 'application/pdf')
            : 'application/pdf';

        $tempSource = null;
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            $tempSource = $response->getFile()->getPathname();
            $content = file_get_contents($tempSource);
        } else {
            $content = $response->getContent();
        }

        if ($content === false || $content === '' || $content === null) {
            throw new \RuntimeException('El PDF generado está vacío');
        }

        $isZip = str_contains((string) $contentType, 'zip')
            || (strlen($content) >= 2 && $content[0] === 'P' && $content[1] === 'K');

        $ext = $isZip ? 'zip' : 'pdf';
        $mime = $isZip ? 'application/zip' : 'application/pdf';
        $nombre = $configuracion->tipo_reporte . '-' . $fechaInicio . '-' . $fechaFin . '-' . time() . '.' . $ext;
        $rutaRelativa = 'temp/reportes/' . $nombre;
        $absolute = $dir . '/' . $nombre;

        file_put_contents($absolute, $content);

        // Algunas exportaciones marcan deleteFileAfterSend; limpiar origen si quedó
        if ($tempSource && file_exists($tempSource) && realpath($tempSource) !== realpath($absolute)) {
            @unlink($tempSource);
        }

        return [
            'ruta' => $rutaRelativa,
            'nombre' => $nombre,
            'mime' => $mime,
        ];
    }

    private function buildExcelExport(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin
    ) {
        $idEmpresa = $configuracion->id_empresa;
        $sucursales = $configuracion->sucursales ?? [];

        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                return new VentasPorVendedorExport($fechaInicio, $fechaFin, $idEmpresa);

            case 'ventas-por-categoria-vendedor':
                return new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $idEmpresa, $configuracion);

            case 'estado-financiero-consolidado-sucursales':
                return new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $idEmpresa);

            case 'detalle-ventas-vendedor':
                return new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $idEmpresa, $sucursales);

            case 'inventario-por-sucursal':
                return $this->buildInventarioExport($configuracion, $fechaInicio, $fechaFin);

            case 'ventas-por-utilidades':
                $export = new VentasPorUtilidadesExport();
                $export->filter(new Request([
                    'id_empresa' => $idEmpresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $sucursales,
                ]));
                return $export;

            case 'cobros-por-vendedor':
                $export = new CobrosPorVendedorExport();
                $export->filter(new Request([
                    'id_empresa' => $idEmpresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $sucursales,
                ]));
                return $export;

            case 'ventas-compras-por-marca-proveedor':
                return new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $idEmpresa,
                    $configuracion,
                    $sucursales
                );

            case 'detalle-ventas-totales':
                return new VentasDesglosadasPorVendedorExport(new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $idEmpresa,
                    'sucursales' => $sucursales,
                ]));

            case 'detalle-ventas-por-producto':
                $export = new VentasDetallesExport();
                $export->filter(new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $idEmpresa,
                    'sucursales' => $sucursales,
                ]));
                return $export;

            case 'detalle-compras-totales':
                $export = new ComprasExport();
                $export->filter(new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $idEmpresa,
                    'sucursales' => $sucursales,
                ]));
                return $export;

            case 'detalle-compras-por-producto':
                $export = new ComprasDetallesExport();
                $export->filter(new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $idEmpresa,
                    'sucursales' => $sucursales,
                ]));
                return $export;

            default:
                throw new \InvalidArgumentException("Tipo de reporte no implementado: {$configuracion->tipo_reporte}");
        }
    }

    private function buildInventarioExport(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin
    ): InventarioExport {
        $sucursales = $configuracion->sucursales ?? [];
        if (empty($sucursales)) {
            $sucursales = Sucursal::where('id_empresa', $configuracion->id_empresa)
                ->pluck('id')
                ->toArray();
        }

        $bodegas = DB::table('sucursal_bodegas')
            ->whereIn('id_sucursal', $sucursales)
            ->pluck('id')
            ->toArray();

        $inventarioData = DB::table('productos as p')
            ->join('inventario as i', 'p.id', '=', 'i.id_producto')
            ->join('sucursal_bodegas as b', 'i.id_bodega', '=', 'b.id')
            ->join('sucursales as s', 'b.id_sucursal', '=', 's.id')
            ->leftJoin('categorias as c', 'p.id_categoria', '=', 'c.id')
            ->leftJoin(DB::raw('(
                SELECT
                    k.id_producto,
                    k.id_inventario,
                    k.precio_unitario,
                    k.costo_unitario
                FROM kardexs k
                INNER JOIN (
                    SELECT
                        id_producto,
                        id_inventario,
                        MAX(fecha) as max_fecha,
                        MAX(id) as max_id
                    FROM kardexs
                    WHERE (precio_unitario > 0 OR costo_unitario > 0)
                    GROUP BY id_producto, id_inventario
                ) k_max ON k.id_producto = k_max.id_producto
                       AND k.id_inventario = k_max.id_inventario
                       AND k.fecha = k_max.max_fecha
                       AND k.id = k_max.max_id
                WHERE (k.precio_unitario > 0 OR k.costo_unitario > 0)
            ) as k_reciente'), function ($join) {
                $join->on('p.id', '=', 'k_reciente.id_producto')
                    ->on('i.id_bodega', '=', 'k_reciente.id_inventario');
            })
            ->leftJoin(DB::raw('(
                SELECT
                    pp.id_producto,
                    CASE
                        WHEN prov.tipo = "Persona" THEN CONCAT(prov.nombre, " ", prov.apellido)
                        WHEN prov.tipo = "Empresa" THEN prov.nombre_empresa
                        ELSE "Sin proveedor"
                    END as nombre_proveedor
                FROM producto_proveedores pp
                LEFT JOIN proveedores prov ON pp.id_proveedor = prov.id
                INNER JOIN (
                    SELECT id_producto, MAX(id) as max_id
                    FROM producto_proveedores
                    GROUP BY id_producto
                ) pp_max ON pp.id_producto = pp_max.id_producto AND pp.id = pp_max.max_id
            ) as prov_info'), 'p.id', '=', 'prov_info.id_producto')
            ->select([
                'p.nombre as nombre_producto',
                'c.nombre as nombre_categoria',
                'p.codigo as codigo_producto',
                'b.nombre as nombre_bodega',
                's.nombre as nombre_sucursal',
                'i.stock as cantidad_actual',
                'i.updated_at as fecha_ultima_actualizacion',
                DB::raw('COALESCE(k_reciente.precio_unitario, p.precio, 0) as precio_unitario'),
                DB::raw('COALESCE(k_reciente.costo_unitario, p.costo_promedio, p.costo, 0) as costo_unitario'),
                DB::raw('COALESCE(prov_info.nombre_proveedor, "Sin proveedor") as nombre_proveedor'),
                DB::raw('(i.stock * COALESCE(k_reciente.costo_unitario, p.costo_promedio, p.costo, 0)) as valor_inventario'),
                DB::raw('(i.stock * COALESCE(k_reciente.precio_unitario, p.precio, 0)) as precio_total'),
                DB::raw('(i.stock * COALESCE(k_reciente.costo_unitario, p.costo_promedio, p.costo, 0)) as costo_total'),
                DB::raw('CASE
                    WHEN i.stock <= i.stock_minimo THEN "Bajo"
                    WHEN i.stock >= i.stock_maximo THEN "Alto"
                    ELSE "Normal"
                END as estado_stock'),
            ])
            ->whereIn('b.id', $bodegas)
            ->where('s.id_empresa', $configuracion->id_empresa)
            ->where('i.stock', '>', 0)
            ->where('i.updated_at', '<=', $fechaFin . ' 23:59:59')
            ->whereNull('i.deleted_at')
            ->whereNull('p.deleted_at')
            ->orderBy('s.nombre')
            ->orderBy('b.nombre')
            ->orderBy('p.nombre')
            ->get();

        $datosPreparados = [];
        foreach ($inventarioData->groupBy('nombre_sucursal') as $sucursalNombre => $datosSucursal) {
            $datosPreparados[] = [
                'tipo' => 'header_sucursal',
                'sucursal' => $sucursalNombre,
                'total_productos' => $datosSucursal->count(),
                'total_bodegas' => $datosSucursal->pluck('nombre_bodega')->unique()->count(),
                'valor_total' => $datosSucursal->sum('valor_inventario'),
            ];

            foreach ($datosSucursal->groupBy('nombre_bodega') as $bodegaNombre => $productos) {
                $datosPreparados[] = [
                    'tipo' => 'header_bodega',
                    'sucursal' => $sucursalNombre,
                    'bodega' => $bodegaNombre,
                    'total_productos' => $productos->count(),
                    'valor_total' => $productos->sum('valor_inventario'),
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
                        'ultima_actualizacion' => $producto->fecha_ultima_actualizacion,
                    ];
                }
            }
        }

        return new InventarioExport($datosPreparados, $configuracion, $fechaInicio, $fechaFin);
    }

    public function buildMailDatos(
        ReporteConfiguracion $configuracion,
        string $fechaInicio,
        string $fechaFin,
        string $filePath,
        bool $esPrueba = false
    ): array {
        $empresa = Empresa::find($configuracion->id_empresa);

        if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
            $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->where('id_empresa', $configuracion->id_empresa)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada')
                ->count();

            $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->where('id_empresa', $configuracion->id_empresa)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada')
                ->sum('total');

            $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                ->where('id_empresa', $configuracion->id_empresa)
                ->where('cotizacion', 0)
                ->distinct('id_vendedor')
                ->where('estado', '!=', 'Anulada')
                ->count('id_vendedor');
        } else {
            $ventasDelDia = 0;
            $totalVentas = 0;
            $vendedoresConVentas = 0;
        }

        $asuntos = [
            'ventas-por-vendedor' => "Reporte de Ventas por Vendedor {$fechaInicio} al {$fechaFin}",
            'ventas-por-categoria-vendedor' => "Reporte de Ventas por Categoría y Vendedor {$fechaInicio} al {$fechaFin}",
            'estado-financiero-consolidado-sucursales' => "Reporte de Estado Financiero Consolidado por Sucursales {$fechaInicio} al {$fechaFin}",
            'detalle-ventas-vendedor' => "Reporte de Detalle de Ventas por Vendedor {$fechaInicio} al {$fechaFin}",
            'inventario-por-sucursal' => "Reporte de Inventario por Sucursal {$fechaInicio} al {$fechaFin}",
            'ventas-por-utilidades' => "Reporte de Ventas por Utilidades {$fechaInicio} al {$fechaFin}",
            'cobros-por-vendedor' => "Reporte de Cobros por Vendedor {$fechaInicio} al {$fechaFin}",
            'ventas-compras-por-marca-proveedor' => "Reporte de Ventas y Compras por Marca y Proveedor {$fechaInicio} al {$fechaFin}",
            'detalle-ventas-totales' => "Reporte de Detalle de Ventas Totales {$fechaInicio} al {$fechaFin}",
            'detalle-ventas-por-producto' => "Reporte de Detalle de Ventas por Producto {$fechaInicio} al {$fechaFin}",
            'detalle-compras-totales' => "Reporte de Detalle de Compras Totales {$fechaInicio} al {$fechaFin}",
            'detalle-compras-por-producto' => "Reporte de Detalle de Compras por Producto {$fechaInicio} al {$fechaFin}",
        ];

        $asunto = $asuntos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;
        if ($esPrueba && !$asunto) {
            $asunto = 'Reporte de Prueba: ' . $configuracion->tipo_reporte . ' - ' . now()->format('d/m/Y');
        }

        return [
            'fecha' => $esPrueba ? now()->format('d/m/Y') : $fechaInicio,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'ventasDelDia' => $ventasDelDia,
            'totalVentas' => $totalVentas,
            'vendedoresConVentas' => $vendedoresConVentas,
            'archivoPath' => $filePath,
            'nombreArchivo' => basename($filePath),
            'asunto' => $asunto,
            'automatico' => !$esPrueba,
            'esPrueba' => $esPrueba,
            'tipo_reporte' => $configuracion->tipo_reporte,
            'empresa' => $empresa->nombre ?? '',
        ];
    }
}
