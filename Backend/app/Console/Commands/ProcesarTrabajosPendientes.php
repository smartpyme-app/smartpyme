<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrabajosPendientes;
use App\Services\MHPruebasMasivasService;
use Illuminate\Support\Facades\Log;

class ProcesarTrabajosPendientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trabajos:procesar {--limite=5} {--duracion=58} {--id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los trabajos pendientes en la base de datos';

    /**
     * Hora de inicio del script
     */
    protected $horaInicio;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->horaInicio = time();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(MHPruebasMasivasService $pruebasMasivasService)
    {

        $limite = $this->option('limite');
        $duracionMaxima = $this->option('duracion');
        
        $this->info("Iniciando procesamiento de trabajos pendientes (límite: $limite, duración máxima: $duracionMaxima segundos)");
        
        if ($this->option('id')) {
            $trabajos = TrabajosPendientes::pendientes()
                ->where('id', $this->option('id'))
                ->take(1)
                ->get();
        } else {
            $trabajos = TrabajosPendientes::pendientes()
                ->orderBy('fecha_creacion', 'asc')
                ->limit($limite)
                ->get();
        }
        
        $this->info("Se encontraron " . count($trabajos) . " trabajos pendientes");
        
        $procesados = 0;
        
        foreach ($trabajos as $trabajo) {
            // Verificar si hemos excedido el tiempo límite
            if (time() - $this->horaInicio >= $duracionMaxima) {
                $this->warn("Se alcanzó el tiempo máximo de ejecución ($duracionMaxima segundos). Terminando.");
                break;
            }
            
            $this->info("Procesando trabajo #{$trabajo->id} de tipo {$trabajo->tipo}");
            
            try {
                // Marcar como en proceso
                $trabajo->estado = 'procesando';
                $trabajo->fecha_inicio = now();
                $trabajo->save();
                
                // Procesar según el tipo de trabajo
                if ($trabajo->tipo === 'pruebas_masivas') {
                    $params = json_decode($trabajo->parametros, true);
                    
                    $this->info("Ejecutando pruebas masivas: {$params['tipo_dte']}, cantidad: {$params['cantidad']}");
                    
                    // Llamar a procesarPruebasMasivas en lugar de ejecutarPruebasMasivas
                    $resultado = $pruebasMasivasService->procesarPruebasMasivas(
                        $params['tipo_dte'],
                        $params['cantidad'],
                        $params['id_documento_base'],
                        $params['id_usuario'],
                        $params['correlativo_inicial'] ?? null
                    );
                    
                    // Guardar resultado
                    $trabajo->estado = 'completado';
                    $trabajo->resultado = json_encode($resultado);
                    $this->info("Pruebas masivas completadas exitosamente");
                }
                // Puedes añadir otros tipos de trabajos aquí
                else {
                    $this->warn("Tipo de trabajo no soportado: {$trabajo->tipo}");
                    $trabajo->estado = 'fallido';
                    $trabajo->resultado = json_encode(['error' => 'Tipo de trabajo no soportado']);
                }
            } catch (\Exception $e) {
                $this->error("Error al procesar trabajo #{$trabajo->id}: " . $e->getMessage());
                Log::error("Error al procesar trabajo #{$trabajo->id}: " . $e->getMessage());
                
                $trabajo->estado = 'fallido';
                $trabajo->resultado = json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Finalizar el trabajo
            $trabajo->fecha_fin = now();
            $trabajo->save();
            
            $procesados++;
        }
        
        $this->info("Procesamiento completado. Se procesaron $procesados trabajos.");
        
        return 0;
    }
}