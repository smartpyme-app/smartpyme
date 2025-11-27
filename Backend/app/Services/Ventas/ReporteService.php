<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Exports\VentasExport;
use App\Exports\VentasDetallesExport;
use App\Exports\VentasAcumuladoExport;
use App\Exports\VentasPorMarcasExport;
use App\Exports\VentasPorUtilidadesExport;
use App\Exports\ReportesAutomaticos\VentasPorVendedor\VentasPorVendedorExport;
use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor\VentasPorCategoriaVendedorExport;
use App\Exports\ReportesAutomaticos\EstadoFinancieroConsolidadoSucursales\EstadoFinancieroConsolidadoSucursalesExport;
use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorExport;
use App\Exports\ReportesAutomaticos\InventarioPorSucursal\InventarioExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReporteService
{
    /**
     * Exportar ventas
     *
     * @param array $filtros
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportarVentas(array $filtros)
    {
        $ventas = new VentasExport();
        $ventas->filter($filtros);

        return Excel::download($ventas, 'ventas.xlsx');
    }

    /**
     * Exportar detalles de ventas
     *
     * @param array $filtros
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportarDetallesVentas(array $filtros)
    {
        $ventas = new VentasDetallesExport();
        $ventas->filter($filtros);

        return Excel::download($ventas, 'ventas-detalles.xlsx');
    }

    /**
     * Exportar acumulado de ventas
     *
     * @param array $filtros
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportarAcumulado(array $filtros)
    {
        $ventas = new VentasAcumuladoExport();
        $ventas->filter($filtros);

        return Excel::download($ventas, 'corte.xlsx');
    }

    /**
     * Exportar ventas por marcas
     *
     * @param array $filtros
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportarPorMarcas(array $filtros)
    {
        $ventas = new VentasPorMarcasExport();
        $ventas->filter($filtros);

        return Excel::download($ventas, 'ventas-por-marcas.xlsx');
    }

    /**
     * Exportar ventas por utilidades
     *
     * @param array $filtros
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportarPorUtilidades(array $filtros)
    {
        $ventas = new VentasPorUtilidadesExport();
        $ventas->filter($filtros);

        return Excel::download($ventas, 'ventas-por-utilidades.xlsx');
    }

    /**
     * Generar reporte diario
     *
     * @param array $opciones
     * @return mixed
     */
    public function generarReporteDiario(array $opciones = [])
    {
        $fecha = Carbon::today()->format('Y-m-d');
        $export = new VentasPorVendedorExport($fecha);

        if (isset($opciones['enviar_correo']) && $opciones['enviar_correo']) {
            return $this->generarReporteParaCorreo($export, "ventas-por-vendedor-{$fecha}.xlsx");
        }

        return Excel::download($export, "ventas-por-vendedor-{$fecha}.xlsx");
    }

    /**
     * Generar reporte programado
     *
     * @param object $configuracion
     * @param object $empresa
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return string
     */
    public function generarReporteProgramado($configuracion, $empresa, string $fechaInicio, string $fechaFin): string
    {
        $export = $this->crearExportPorTipo($configuracion, $fechaInicio, $fechaFin, $empresa->id);
        $filename = "{$configuracion->tipo_reporte}-{$fechaInicio}.xlsx";

        return $this->generarReporteParaCorreo($export, $filename);
    }

    /**
     * Crear export según tipo de reporte
     *
     * @param object $configuracion
     * @param string $fechaInicio
     * @param string $fechaFin
     * @param int $idEmpresa
     * @return mixed
     */
    public function crearExportPorTipo($configuracion, string $fechaInicio, string $fechaFin, int $idEmpresa)
    {
        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                return new VentasPorVendedorExport($fechaInicio, $fechaFin, $idEmpresa);
            case 'ventas-por-categoria-vendedor':
                return new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $idEmpresa, $configuracion);
            case 'estado-financiero-consolidado-sucursales':
                return new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $idEmpresa);
            case 'detalle-ventas-vendedor':
                return new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $idEmpresa, $configuracion->sucursales);
            case 'inventario-por-sucursal':
                return new InventarioExport($fechaInicio, $fechaFin, $idEmpresa, $configuracion);
            default:
                throw new \Exception('Tipo de reporte no implementado');
        }
    }

    /**
     * Generar reporte para envío por correo
     *
     * @param mixed $export
     * @param string $filename
     * @return string
     */
    public function generarReporteParaCorreo($export, string $filename): string
    {
        $relativePath = "reportes/{$filename}";

        $directory = public_path('img/reportes');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        Storage::disk('public')->put($relativePath, '');
        Excel::store($export, $relativePath, 'public');

        $filePath = public_path('img/' . $relativePath);

        if (!file_exists($filePath)) {
            Log::error("Archivo no encontrado en: {$filePath}");
            $alternativePath = storage_path('app/public/' . $relativePath);
            Log::info("Intentando ruta alternativa: {$alternativePath}");

            if (file_exists($alternativePath)) {
                $filePath = $alternativePath;
            } else {
                throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
            }
        }

        return $filePath;
    }

    /**
     * Obtener estadísticas de ventas para reporte
     *
     * @param string $fechaInicio
     * @param string $fechaFin
     * @param int|null $idEmpresa
     * @return array
     */
    public function obtenerEstadisticasVentas(string $fechaInicio, string $fechaFin, ?int $idEmpresa = null): array
    {
        $query = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('cotizacion', 0)
            ->where('estado', '!=', 'Anulada');

        if ($idEmpresa) {
            $query->where('id_empresa', $idEmpresa);
        }

        return [
            'ventasDelDia' => $query->count(),
            'totalVentas' => $query->sum('total'),
            'vendedoresConVentas' => $query->distinct('id_vendedor')->count('id_vendedor'),
        ];
    }
}

