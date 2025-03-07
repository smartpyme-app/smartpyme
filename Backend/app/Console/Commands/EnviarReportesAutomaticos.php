<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Ventas\VentasController;
use Illuminate\Console\Command;
use App\Http\Controllers\Reportes\VentasPorVendedorController;
use App\Models\Admin\Empresa;
use App\Models\Admin\ReporteConfiguracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EnviarReportesAutomaticos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reportes:enviar {--tipo= : Tipo específico de reporte}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía los reportes automáticos configurados';

    /**
     * Create a new command instance.
     *
     * @return void
     */
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
        $now = Carbon::now();
        $horaActual = $now->format('H:i');
        
        $this->info("Ejecutando envío de reportes automáticos a las {$horaActual}");
        
        $query = ReporteConfiguracion::where('activo', true);
        
        if ($tipo) {
            $query->where('tipo_reporte', $tipo);
        }
        
        // Obtener todas las configuraciones activas
        $configuraciones = $query->get();
        
        $reportesEnviados = 0;
        
        foreach ($configuraciones as $configuracion) {
            if (!$configuracion->debeEnviarseHoy()) {
                continue;
            }
            
            foreach (['envio_matutino', 'envio_mediodia', 'envio_nocturno'] as $horario) {
                if ($configuracion->$horario) {
                    $horaAtributo = 'hora_' . substr($horario, 6); 
                    $horaConfiguracion = $configuracion->$horaAtributo;
                     // Comparar la hora actual con la configurada (con 5 minutos de tolerancia)
                    $horaEnvio = Carbon::createFromFormat('H:i', substr($horaConfiguracion, 0, 5));
                    $diferenciaMinutos = abs($now->diffInMinutes($horaEnvio));
                    
                    if ($diferenciaMinutos <= 5) {
                        // Es hora de enviar este reporte
                        $this->info("Enviando reporte: {$configuracion->tipo_reporte} (ID: {$configuracion->id})");
                        
                        try {
                            $this->enviarReporte($configuracion);
                            $reportesEnviados++;
                        } catch (\Exception $e) {
                            $this->error("Error al enviar reporte ID {$configuracion->id}: " . $e->getMessage());
                            Log::error("Error al enviar reporte automático: " . $e->getMessage(), [
                                'configuracion_id' => $configuracion->id,
                                'tipo_reporte' => $configuracion->tipo_reporte,
                                'horario' => $horario
                            ]);
                        }
                        
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
        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                $controller = new VentasController();
                $empresa = Empresa::find($configuracion->id_empresa);
                return $controller->enviarReporteProgramado($configuracion, $empresa);
                
            // Implementar otros tipos de reportes aquí
                
            default:
                throw new \Exception("Tipo de reporte no implementado: {$configuracion->tipo_reporte}");
        }
    }
}