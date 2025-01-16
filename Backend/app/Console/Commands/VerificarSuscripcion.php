<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Suscripcion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VerificarSuscripciones extends Command
{
    protected $signature = 'suscripciones:verificar';
    protected $description = 'Verifica y actualiza el estado de las suscripciones y períodos de prueba';

    public function handle()
    {
        $this->info('Iniciando verificación de suscripciones...');
        Log::channel('suscripciones')->info('Iniciando verificación de suscripciones');

        try {
            // 1. Verificar períodos de prueba
            $this->verificarPeriodosPrueba();

            // 2. Verificar suscripciones por vencer
            $this->verificarSuscripcionesPorVencer();

            // 3. Verificar suscripciones vencidas
            $this->verificarSuscripcionesVencidas();

            $this->info('Verificación completada exitosamente');
            Log::channel('suscripciones')->info('Verificación completada exitosamente');

        } catch (\Exception $e) {
            $this->error("Error en la verificación: {$e->getMessage()}");
            Log::channel('suscripciones')->error("Error en la verificación: {$e->getMessage()}");
            throw $e;
        }
    }

    private function verificarPeriodosPrueba()
    {
        $this->info('Verificando períodos de prueba...');
        
        $suscripcionesEnPrueba = Suscripcion::where('estado', config('constants.ESTADO_SUSCRIPCION_EN_PRUEBA'))
            ->where('fin_periodo_prueba', '<=', Carbon::now())
            ->get();

        foreach ($suscripcionesEnPrueba as $suscripcion) {
            $this->procesarFinPeriodoPrueba($suscripcion);
        }
    }

    private function procesarFinPeriodoPrueba(Suscripcion $suscripcion)
    {
        $this->info("Procesando fin de período de prueba para suscripción ID: {$suscripcion->id}");
        
        try {
            // Si la suscripción tiene configurado pago automático
            if ($suscripcion->tiene_pago_automatico) {
                // Intentar realizar el primer cobro
                $resultado = $this->realizarPrimerCobro($suscripcion);
                
                if ($resultado) {
                    $suscripcion->update([
                        'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                        'fecha_ultimo_pago' => Carbon::now(),
                        'fecha_proximo_pago' => Carbon::now()->addDays($suscripcion->plan->duracion_dias)
                    ]);
                } else {
                    $this->marcarComoPendiente($suscripcion);
                }
            } else {
                // Si no tiene pago automático, marcar como pendiente
                $this->marcarComoPendiente($suscripcion);
            }

            // Notificar al usuario
            $this->notificarFinPeriodoPrueba($suscripcion);

        } catch (\Exception $e) {
            Log::channel('suscripciones')->error(
                "Error procesando fin de período de prueba {$suscripcion->id}: {$e->getMessage()}"
            );
        }
    }

    private function marcarComoPendiente(Suscripcion $suscripcion)
    {
        $suscripcion->update([
            'estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE'),
            'intentos_cobro' => 0,
            'ultimo_intento_cobro' => null
        ]);
    }

    private function notificarFinPeriodoPrueba(Suscripcion $suscripcion)
    {
        // Lógica de notificación según el estado final de la suscripción
        $mensaje = $suscripcion->estado === config('constants.ESTADO_SUSCRIPCION_ACTIVO')
            ? "Tu período de prueba ha finalizado y tu suscripción está activa"
            : "Tu período de prueba ha finalizado. Por favor, realiza el pago para continuar";

        // Mail::to($suscripcion->usuario->email)->send(new NotificacionFinPrueba($suscripcion, $mensaje));
    }

    private function verificarSuscripcionesPorVencer()
    {
        $porVencer = Suscripcion::where('estado', config('constants.ESTADO_SUSCRIPCION_ACTIVO'))
            ->where('fecha_proximo_pago', '<=', Carbon::now()->addDays(3))
            ->where('fecha_proximo_pago', '>', Carbon::now())
            ->get();

        foreach ($porVencer as $suscripcion) {
            $this->procesarSuscripcionPorVencer($suscripcion);
        }
    }

    private function verificarSuscripcionesVencidas()
    {
        $vencidas = Suscripcion::where('estado', config('constants.ESTADO_SUSCRIPCION_ACTIVO'))
            ->where('fecha_proximo_pago', '<', Carbon::now())
            ->get();

        foreach ($vencidas as $suscripcion) {
            $this->procesarSuscripcionVencida($suscripcion);
        }
    }

}