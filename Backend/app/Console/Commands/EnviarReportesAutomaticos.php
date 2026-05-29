<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Ventas\VentasController;
use Illuminate\Console\Command;
use App\Models\Admin\Empresa;
use App\Models\Admin\ReporteConfiguracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnviarReportesAutomaticos extends Command
{
  
    protected $signature = 'reportes:enviar {--tipo= : Tipo específico de reporte} {--force : Forzar envío sin considerar cache}';


    protected $description = 'Envía los reportes automáticos configurados';

  
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tipo = $this->option('tipo');
        $force = $this->option('force');
        $now = Carbon::now();
        $horaActual = $now->format('H:i');
        $fechaActual = $now->format('Y-m-d');
        
        $this->info("Ejecutando envío de reportes automáticos a las {$horaActual}");
        
        $query = ReporteConfiguracion::where('activo', true);
        
        if ($tipo) {
            $query->where('tipo_reporte', $tipo);
        }
        
 
        $configuraciones = $query->get();
        
        $reportesEnviados = 0;
        
        foreach ($configuraciones as $configuracion) {

            if (!$configuracion->debeEnviarseHoy()) {
                $this->info("Reporte {$configuracion->id} no debe enviarse hoy según frecuencia.");
                continue;
            }
            
            // Verificar cada horario de envío
            foreach (['envio_matutino', 'envio_mediodia', 'envio_nocturno'] as $horario) {
                if ($configuracion->$horario) {
                    $horaAtributo = 'hora_' . substr($horario, 6); // Extrae 'matutino', 'mediodia' o 'nocturno'
                    $horaConfiguracion = $configuracion->$horaAtributo;
                    
                    // Crear una clave única para el cache
                    $cacheKey = "reporte_{$configuracion->id}_{$horario}_{$fechaActual}";
                    
                    // Comparar la hora actual con la configurada
                    $horaEnvio = Carbon::createFromFormat('H:i', substr($horaConfiguracion, 0, 5));
                    $diferenciaMinutos = abs($now->diffInMinutes($horaEnvio));
                    
                    if ($diferenciaMinutos <= 5) {
                        // Verificar si este reporte ya fue enviado hoy en este horario
                        if (Cache::has($cacheKey) && !$force) {
                            $this->info("Reporte ya fue enviado hoy ({$cacheKey}). Omitiendo.");
                            continue;
                        }
                        
                        // Es hora de enviar este reporte
                        $this->info("Enviando reporte: {$configuracion->tipo_reporte} (ID: {$configuracion->id})");
                        
                        try {
                            $this->enviarReporte($configuracion);
                            $reportesEnviados++;
                            
                            // Marcar este reporte como enviado para hoy
                            Cache::put($cacheKey, true, Carbon::now()->endOfDay());
                            
                            $this->info("Reporte enviado y marcado como completado: {$cacheKey}");
                        } catch (\Exception $e) {
                            $this->error("Error al enviar reporte ID {$configuracion->id}: " . $e->getMessage());
                            Log::error("Error al enviar reporte automático: " . $e->getMessage(), [
                                'configuracion_id' => $configuracion->id,
                                'tipo_reporte' => $configuracion->tipo_reporte,
                                'horario' => $horario
                            ]);
                        }
                        
                        // Salir del bucle de horarios para esta configuración
                        break;
                    }
                }
            }
        }
        
        $this->info("Proceso completado. Reportes enviados: {$reportesEnviados}");
        
        return 0;
    }
    
    private function enviarReporte(ReporteConfiguracion $configuracion)
    {

        $fecha_inicio = Carbon::today()->format('Y-m-d');
        $fecha_fin = Carbon::today()->format('Y-m-d');
        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'ventas-por-categoria-vendedor':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'estado-financiero-consolidado-sucursales':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'detalle-ventas-vendedor':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'inventario-por-sucursal':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'ventas-por-utilidades':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'cobros-por-vendedor':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'ventas-compras-por-marca-proveedor':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'detalle-ventas-totales':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            case 'detalle-ventas-por-producto':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa, $fecha_inicio, $fecha_fin);
            default:
                throw new \Exception("Tipo de reporte no implementado: {$configuracion->tipo_reporte}");
        }
    }
}