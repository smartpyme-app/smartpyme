<?php

namespace App\Console\Commands;

use App\Jobs\FidelizacionCliente\ProcesarExpiracionPuntos;
use App\Services\FidelizacionCliente\ExpiracionPuntosService;
use Illuminate\Console\Command;

class ProcesarExpiracionPuntosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fidelizacion:procesar-expiracion-puntos {--sync : Ejecutar de forma síncrona en lugar de encolar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los puntos de fidelización que han expirado y los marca como vencidos';

    /**
     * Execute the console command.
     */
    public function handle(ExpiracionPuntosService $expiracionService): int
    {
        $this->info('🔄 Procesando expiración de puntos de fidelización...');

        if ($this->option('sync')) {
            $resultado = $expiracionService->procesarExpiraciones();

            $this->info("✅ Completado: {$resultado['procesadas']} ganancias procesadas, {$resultado['puntos_vencidos']} puntos vencidos");

            if (!empty($resultado['errores'])) {
                $this->warn('⚠️  Errores encontrados: ' . count($resultado['errores']));
                foreach ($resultado['errores'] as $error) {
                    $this->error("  - Ganancia #{$error['ganancia_id']}: {$error['mensaje']}");
                }
            }

            return self::SUCCESS;
        }

        ProcesarExpiracionPuntos::dispatch();
        $this->info('✅ Job encolado. Ejecute queue:work para procesarlo.');

        return self::SUCCESS;
    }
}
