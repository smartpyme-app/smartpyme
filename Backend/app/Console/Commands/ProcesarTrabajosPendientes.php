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

    public function handle()
    {
        $limite = $this->option('limite');
        $id = $this->option('id');

        $query = TrabajosPendientes::where('estado', 'pendiente')
            ->where('tipo', 'pruebas_masivas');

        if ($id) {
            $query->where('id', $id);
        }

        $trabajos = $query->orderBy('fecha_creacion', 'asc')
            ->limit($limite)
            ->get();

        if ($trabajos->isEmpty()) {
            $this->info('No hay trabajos pendientes para procesar.');
            return;
        }

        $this->info("Procesando {$trabajos->count()} trabajo(s) pendiente(s)...");

        foreach ($trabajos as $trabajo) {
            $this->procesarTrabajo($trabajo);
        }

        $this->info('Procesamiento completado.');
    }

    private function procesarTrabajo(TrabajosPendientes $trabajo)
    {
        try {
            // Marcar como en proceso
            $trabajo->update([
                'estado' => 'procesando',
                'fecha_inicio' => now()
            ]);

            $this->info("Procesando trabajo ID: {$trabajo->id}");

            // Decodificar parámetros
            $parametros = json_decode($trabajo->parametros, true);

            // Crear instancia del servicio
            $service = new MHPruebasMasivasService();

            // EJECUTAR EL PROCESO ACTUALIZADO
            $resultado = $service->procesarPruebasMasivas(
                $parametros['tipo_dte'],
                $parametros['cantidad'],
                $parametros['id_documento_base'] ?? null,
                $parametros['id_usuario'],
                $parametros['correlativo_inicial'] ?? null
            );

            // Actualizar el trabajo según el resultado
            if ($resultado['success']) {
                $trabajo->update([
                    'estado' => 'completado',
                    'resultado' => json_encode($resultado),
                    'fecha_fin' => now()
                ]);

                $this->info("✓ Trabajo {$trabajo->id} completado exitosamente");
                
                // Log adicional para CCF con notas automáticas
                if ($parametros['tipo_dte'] === '03') {
                    $this->info("  → CCF generados con notas automáticas incluidas");
                }
            } else {
                $trabajo->update([
                    'estado' => 'fallido',
                    'resultado' => json_encode($resultado),
                    'fecha_fin' => now()
                ]);

                $this->error("✗ Trabajo {$trabajo->id} falló: " . $resultado['message']);
            }

        } catch (\Exception $e) {
            // Marcar como fallido en caso de excepción
            $trabajo->update([
                'estado' => 'fallido',
                'resultado' => json_encode(['error' => $e->getMessage()]),
                'fecha_fin' => now()
            ]);

            $this->error("✗ Error en trabajo {$trabajo->id}: " . $e->getMessage());
            Log::error("Error procesando trabajo {$trabajo->id}: " . $e->getMessage());
        }
    }
}