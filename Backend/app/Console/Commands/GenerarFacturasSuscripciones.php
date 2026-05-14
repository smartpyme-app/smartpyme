<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Suscripcion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerarFacturasSuscripciones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facturas:generar-suscripciones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar las facturas correspondientes al inicio de mes para las suscripciones activas.';

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
        $this->info('Iniciando la generación de facturas de suscripciones...');
        Log::channel('facturacion')->info('Iniciando la generación de facturas de suscripciones...');

        try {
            // Filtrar suscripciones activas y método de pago distinto a N1co (1) o null
            $suscripciones = Suscripcion::where('estado', 'activo')
                ->where(function ($query) {
                    $query->whereNull('metodo_pago')
                          ->orWhere('metodo_pago', '!=', '1');
                })
                ->get();

            $hoy = Carbon::now();

            foreach ($suscripciones as $suscripcion) {
                if ($suscripcion->tipo_plan === 'anual') {
                    if ($suscripcion->fecha_proximo_pago) {
                        $fechaProximoPago = Carbon::parse($suscripcion->fecha_proximo_pago);
                        
                        // Emitir solo si mes y año actual coinciden con mes y año de fecha_proximo_pago
                        if ($hoy->year === $fechaProximoPago->year && $hoy->month === $fechaProximoPago->month) {
                            $this->emitirFactura($suscripcion);
                        }
                    }
                } else {
                    // Planes no anuales se emiten normalmente
                    $this->emitirFactura($suscripcion);
                }
            }

            $this->info('Generación de facturas de suscripciones completada exitosamente.');
            Log::channel('facturacion')->info('Generación de facturas de suscripciones completada exitosamente.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error durante la generación de facturas: ' . $e->getMessage());
            Log::channel('facturacion')->error('Error durante la generación de facturas de suscripciones: ' . $e->getMessage());
            
            return 1;
        }
    }

    /**
     * Emite la factura para la suscripción dada.
     *
     * @param Suscripcion $suscripcion
     * @return void
     */
    private function emitirFactura(Suscripcion $suscripcion)
    {
        try {
            // Lógica de emisión de factura en el futuro.
            // Por ahora, solo se imprime en consola y se guarda en log.
            $mensaje = "Factura generada para la suscripción ID: {$suscripcion->id}, Empresa ID: {$suscripcion->empresa_id}";
            
            $this->info($mensaje);
            Log::channel('facturacion')->info($mensaje);
            
        } catch (\Exception $e) {
            $errorMensaje = "Error al emitir factura para la suscripción ID: {$suscripcion->id} - " . $e->getMessage();
            
            $this->error($errorMensaje);
            Log::channel('facturacion')->error($errorMensaje);
        }
    }
}
