<?php

namespace App\Console\Commands;

use App\Jobs\GenerarReporteAutomaticoJob;
use App\Models\Admin\ReporteConfiguracion;
use App\Models\Admin\ReporteExportacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnviarReportesAutomaticos extends Command
{
    protected $signature = 'reportes:enviar {--tipo= : Tipo específico de reporte} {--force : Forzar envío sin considerar cache}';

    protected $description = 'Encola los reportes automáticos configurados';

    public function handle()
    {
        $tipo = $this->option('tipo');
        $force = $this->option('force');
        $now = Carbon::now();
        $horaActual = $now->format('H:i');
        $fechaActual = $now->format('Y-m-d');

        $this->info("Encolando reportes automáticos a las {$horaActual}");

        $query = ReporteConfiguracion::where('activo', true);

        if ($tipo) {
            $query->where('tipo_reporte', $tipo);
        }

        $configuraciones = $query->get();
        $reportesEncolados = 0;

        foreach ($configuraciones as $configuracion) {
            if (!$configuracion->debeEnviarseHoy()) {
                $this->info("Reporte {$configuracion->id} no debe enviarse hoy según frecuencia.");
                continue;
            }

            foreach (['envio_matutino', 'envio_mediodia', 'envio_nocturno'] as $horario) {
                if (!$configuracion->$horario) {
                    continue;
                }

                $horaAtributo = 'hora_' . substr($horario, 6);
                $horaConfiguracion = $configuracion->$horaAtributo;
                $cacheKey = "reporte_{$configuracion->id}_{$horario}_{$fechaActual}";

                $horaEnvio = Carbon::createFromFormat('H:i', substr($horaConfiguracion, 0, 5));
                $diferenciaMinutos = abs($now->diffInMinutes($horaEnvio));

                if ($diferenciaMinutos > 5) {
                    continue;
                }

                if (Cache::has($cacheKey) && !$force) {
                    $this->info("Reporte ya fue encolado hoy ({$cacheKey}). Omitiendo.");
                    continue;
                }

                $this->info("Encolando reporte: {$configuracion->tipo_reporte} (ID: {$configuracion->id})");

                try {
                    $this->encolarReporte($configuracion, $fechaActual);
                    $reportesEncolados++;
                    Cache::put($cacheKey, true, Carbon::now()->endOfDay());
                    $this->info("Reporte encolado: {$cacheKey}");
                } catch (\Exception $e) {
                    $this->error("Error al encolar reporte ID {$configuracion->id}: " . $e->getMessage());
                    Log::error('Error al encolar reporte automático: ' . $e->getMessage(), [
                        'configuracion_id' => $configuracion->id,
                        'tipo_reporte' => $configuracion->tipo_reporte,
                        'horario' => $horario,
                    ]);
                }

                break;
            }
        }

        $this->info("Proceso completado. Reportes encolados: {$reportesEncolados}");

        return 0;
    }

    private function encolarReporte(ReporteConfiguracion $configuracion, string $fechaActual): void
    {
        $tipos = [
            'ventas-por-vendedor',
            'ventas-por-categoria-vendedor',
            'estado-financiero-consolidado-sucursales',
            'detalle-ventas-vendedor',
            'inventario-por-sucursal',
            'ventas-por-utilidades',
            'cobros-por-vendedor',
            'ventas-compras-por-marca-proveedor',
            'detalle-ventas-totales',
            'detalle-ventas-por-producto',
            'detalle-compras-totales',
            'detalle-compras-por-producto',
        ];

        if (!in_array($configuracion->tipo_reporte, $tipos, true)) {
            throw new \Exception("Tipo de reporte no implementado: {$configuracion->tipo_reporte}");
        }

        $exportacion = ReporteExportacion::create([
            'id_empresa' => $configuracion->id_empresa,
            'id_usuario' => null,
            'id_configuracion' => $configuracion->id,
            'modo' => ReporteExportacion::MODO_EMAIL,
            'formato' => ReporteExportacion::FORMATO_EXCEL,
            'estado' => ReporteExportacion::ESTADO_PENDING,
            'fecha_inicio' => $fechaActual,
            'fecha_fin' => $fechaActual,
            'sucursales' => $configuracion->sucursales,
            'destinatarios' => $configuracion->destinatarios,
        ]);

        GenerarReporteAutomaticoJob::dispatch($exportacion->id);
    }
}
