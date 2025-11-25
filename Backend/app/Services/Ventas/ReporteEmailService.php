<?php

namespace App\Services\Ventas;

use App\Models\Admin\Empresa;
use App\Mail\ReporteVentasPorVendedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReporteEmailService
{
    protected $reporteService;

    public function __construct(ReporteService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    /**
     * Enviar reporte diario por correo
     *
     * @param array $destinatarios
     * @return array
     */
    public function enviarReporteDiario(array $destinatarios = ['cristian.g@smartpyme.sv']): array
    {
        try {
            $fecha = Carbon::today()->format('Y-m-d');
            $filePath = $this->reporteService->generarReporteDiario(['enviar_correo' => true]);
            $estadisticas = $this->reporteService->obtenerEstadisticasVentas($fecha, $fecha);

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'ventasDelDia' => $estadisticas['ventasDelDia'],
                'totalVentas' => $estadisticas['totalVentas'],
                'vendedoresConVentas' => $estadisticas['vendedoresConVentas'],
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath)
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            return ['message' => 'Reporte enviado correctamente'];
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte diario: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar reporte programado por correo
     *
     * @param object $configuracion
     * @param object $empresa
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return bool
     */
    public function enviarReporteProgramado($configuracion, $empresa, string $fechaInicio, string $fechaFin): bool
    {
        try {
            $filePath = $this->reporteService->generarReporteProgramado($configuracion, $empresa, $fechaInicio, $fechaFin);

            $estadisticas = [];
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $estadisticas = $this->reporteService->obtenerEstadisticasVentas($fechaInicio, $fechaFin, $empresa->id);
            } else {
                $estadisticas = ['ventasDelDia' => 0, 'totalVentas' => 0, 'vendedoresConVentas' => 0];
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;

            $datos = [
                'fecha' => $fechaInicio,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $estadisticas['ventasDelDia'],
                'totalVentas' => $estadisticas['totalVentas'],
                'vendedoresConVentas' => $estadisticas['vendedoresConVentas'],
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto,
                'automatico' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            $destinatarios = $configuracion->destinatarios;
            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            Log::info("Reporte enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);

            unlink($filePath);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte programado: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Enviar reporte de prueba por correo
     *
     * @param object $configuracion
     * @param array $destinatarios
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return bool
     */
    public function enviarReportePrueba($configuracion, array $destinatarios, string $fechaInicio, string $fechaFin): bool
    {
        try {
            $export = $this->reporteService->crearExportPorTipo($configuracion, $fechaInicio, $fechaFin, $configuracion->id_empresa);
            $filename = "{$configuracion->tipo_reporte}-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            $filePath = $this->reporteService->generarReporteParaCorreo($export, $filename);

            $estadisticas = [];
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $estadisticas = $this->reporteService->obtenerEstadisticasVentas($fechaInicio, $fechaFin);
            } else {
                $estadisticas = ['ventasDelDia' => 0, 'totalVentas' => 0, 'vendedoresConVentas' => 0];
            }

            $empresa = Empresa::find($configuracion->id_empresa);

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $estadisticas['ventasDelDia'],
                'totalVentas' => $estadisticas['totalVentas'],
                'vendedoresConVentas' => $estadisticas['vendedoresConVentas'],
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $configuracion->asunto_correo ?: "Reporte de Prueba: Ventas por Vendedor - " . Carbon::today()->format('d/m/Y'),
                'esPrueba' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            Log::info("Reporte de prueba enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);

            unlink($filePath);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte de prueba: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }
}


