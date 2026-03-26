<?php

namespace App\Jobs\FidelizacionCliente;

use App\Services\FidelizacionCliente\ExpiracionPuntosService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcesarExpiracionPuntos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos
     */
    public int $tries = 3;

    /**
     * Timeout en segundos
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ExpiracionPuntosService $expiracionService): void
    {
        Log::info('Iniciando procesamiento de expiración de puntos');

        $resultado = $expiracionService->procesarExpiraciones();

        Log::info('Procesamiento de expiración de puntos completado', [
            'ganancias_procesadas' => $resultado['procesadas'],
            'puntos_vencidos' => $resultado['puntos_vencidos'],
            'errores' => count($resultado['errores'])
        ]);

        if (!empty($resultado['errores'])) {
            Log::warning('Errores durante la expiración de puntos', $resultado['errores']);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job ProcesarExpiracionPuntos falló', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
