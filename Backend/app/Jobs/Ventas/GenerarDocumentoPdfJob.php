<?php

namespace App\Jobs\Ventas;

use App\Services\Ventas\DocumentoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarDocumentoPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ventaId;
    protected $documentoService;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutos

    /**
     * Create a new job instance.
     *
     * @param int $ventaId
     * @return void
     */
    public function __construct(int $ventaId)
    {
        $this->ventaId = $ventaId;
    }

    /**
     * Execute the job.
     *
     * @param DocumentoService $documentoService
     * @return void
     */
    public function handle(DocumentoService $documentoService)
    {
        try {
            Log::info("Iniciando generación de PDF para venta: {$this->ventaId}");
            
            $resultado = $documentoService->generarDocumento($this->ventaId);
            
            Log::info("PDF generado exitosamente para venta: {$this->ventaId}");
            
            return $resultado;
        } catch (\Exception $e) {
            Log::error("Error al generar PDF para venta {$this->ventaId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Job GenerarDocumentoPdfJob falló para venta {$this->ventaId}: " . $exception->getMessage());
    }
}


