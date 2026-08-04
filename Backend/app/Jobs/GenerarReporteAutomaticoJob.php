<?php

namespace App\Jobs;

use App\Mail\ReporteVentasPorVendedor;
use App\Models\Admin\ReporteExportacion;
use App\Services\Reportes\ReporteAutomaticoGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerarReporteAutomaticoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 3600;

    protected $exportacionId;

    public function __construct(int $exportacionId)
    {
        $this->exportacionId = $exportacionId;
    }

    public function handle(ReporteAutomaticoGenerator $generator)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');

        $exportacion = ReporteExportacion::find($this->exportacionId);
        if (!$exportacion) {
            Log::warning("GenerarReporteAutomaticoJob: exportación {$this->exportacionId} no encontrada");
            return;
        }

        $exportacion->estado = ReporteExportacion::ESTADO_PROCESSING;
        $exportacion->error = null;
        $exportacion->save();

        try {
            $configuracion = $exportacion->configuracion;
            if (!$configuracion) {
                throw new \RuntimeException('Configuración de reporte no encontrada');
            }

            $fechaInicio = $exportacion->fecha_inicio->format('Y-m-d');
            $fechaFin = $exportacion->fecha_fin->format('Y-m-d');

            $resultado = $generator->generate(
                $configuracion,
                $fechaInicio,
                $fechaFin,
                $exportacion->sucursales,
                $exportacion->formato
            );

            $absolute = storage_path('app/' . $resultado['ruta']);

            $exportacion->ruta_archivo = $resultado['ruta'];
            $exportacion->nombre_archivo = $resultado['nombre'];

            if ($exportacion->modo === ReporteExportacion::MODO_EMAIL) {
                $destinatarios = $exportacion->destinatarios ?: ($configuracion->destinatarios ?? []);
                if (empty($destinatarios)) {
                    throw new \RuntimeException('No hay destinatarios para enviar el reporte');
                }

                $esPrueba = !empty($exportacion->id_usuario);
                $datos = $generator->buildMailDatos(
                    $configuracion,
                    $fechaInicio,
                    $fechaFin,
                    $absolute,
                    $esPrueba
                );

                Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

                if (file_exists($absolute)) {
                    @unlink($absolute);
                }

                $exportacion->ruta_archivo = null;
                $exportacion->estado = ReporteExportacion::ESTADO_DONE;
                $exportacion->save();

                Log::info('Reporte automático enviado por cola', [
                    'exportacion_id' => $exportacion->id,
                    'tipo' => $configuracion->tipo_reporte,
                    'destinatarios' => $destinatarios,
                ]);
                return;
            }

            $exportacion->estado = ReporteExportacion::ESTADO_DONE;
            $exportacion->save();

            Log::info('Reporte automático listo para descarga', [
                'exportacion_id' => $exportacion->id,
                'archivo' => $resultado['nombre'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en GenerarReporteAutomaticoJob: ' . $e->getMessage(), [
                'exportacion_id' => $this->exportacionId,
                'trace' => $e->getTraceAsString(),
            ]);

            $exportacion->estado = ReporteExportacion::ESTADO_FAILED;
            $exportacion->error = $e->getMessage();
            $exportacion->save();

            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        $exportacion = ReporteExportacion::find($this->exportacionId);
        if ($exportacion && $exportacion->estado !== ReporteExportacion::ESTADO_FAILED) {
            $exportacion->estado = ReporteExportacion::ESTADO_FAILED;
            $exportacion->error = $exception->getMessage();
            $exportacion->save();
        }

        Log::error('GenerarReporteAutomaticoJob falló definitivamente: ' . $exception->getMessage(), [
            'exportacion_id' => $this->exportacionId,
        ]);
    }
}
