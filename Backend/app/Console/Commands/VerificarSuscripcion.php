<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Notifications\SuscripcionNotification;

class VerificarSuscripcion extends Command
{
    protected $signature = 'suscripciones:verificar';
    protected $description = 'Verifica y actualiza el estado de las suscripciones, períodos de prueba y envía notificaciones';

    private const DIAS_PERIODO_PRUEBA = 3;
    private const DIAS_PRIMERA_ALERTA = 3;
    private const DIAS_ALERTA_CRITICA = 7;
    private const DIAS_DESACTIVACION = 10;

    public function handle()
    {
        $this->info('Iniciando verificación de suscripciones...');
        Log::channel('suscripciones')->info('Iniciando verificación de suscripciones');

        try {
            $this->verificarPeriodosPrueba();
            $this->verificarSuscripcionesVencidas();
            
            $this->info('Verificación completada exitosamente');
            Log::channel('suscripciones')->info('Verificación completada exitosamente');
        } catch (\Exception $e) {
            Log::channel('suscripciones')->error("Error en verificación: {$e->getMessage()}");
            $this->error("Error en la verificación: {$e->getMessage()}");
        }
    }

    private function verificarPeriodosPrueba()
    {
        $this->info('Verificando períodos de prueba...');
        
        $suscripcionesEnPrueba = Suscripcion::where('estado', config('constants.ESTADO_SUSCRIPCION_EN_PRUEBA'))
            ->where('fin_periodo_prueba', '<=', now())
            ->get();

        foreach ($suscripcionesEnPrueba as $suscripcion) {
            $this->procesarFinPeriodoPrueba($suscripcion);
        }
    }

    private function verificarSuscripcionesVencidas()
    {
        $suscripciones = Suscripcion::where('estado', '!=', config('constants.ESTADO_SUSCRIPCION_CANCELADO'))
            ->where('fecha_proximo_pago', '<', now())
            ->get();

        foreach ($suscripciones as $suscripcion) {
            $diasVencidos = now()->diffInDays($suscripcion->fecha_proximo_pago);
            
            $this->manejarSuscripcionVencida($suscripcion, $diasVencidos);
        }
    }

    private function manejarSuscripcionVencida(Suscripcion $suscripcion, int $diasVencidos)
    {
        // Primera alerta (3 días)
        if ($diasVencidos >= self::DIAS_PRIMERA_ALERTA && $diasVencidos < self::DIAS_ALERTA_CRITICA) {
            $this->enviarNotificacionVencimiento($suscripcion, 'primera_alerta');
            $suscripcion->update(['estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE')]);
        }
        
        // Alerta crítica (7 días)
        elseif ($diasVencidos >= self::DIAS_ALERTA_CRITICA && $diasVencidos < self::DIAS_DESACTIVACION) {
            $this->enviarNotificacionVencimiento($suscripcion, 'alerta_critica');
            $suscripcion->update(['estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE')]);
        }
        
        // Desactivación (10 días)
        elseif ($diasVencidos >= self::DIAS_DESACTIVACION) {
            $this->desactivarCuenta($suscripcion);
        }
    }

    private function procesarFinPeriodoPrueba(Suscripcion $suscripcion)
    {
        $this->info("Procesando fin de período de prueba para suscripción ID: {$suscripcion->id}");
        
        try {
            // Actualizar estado de la suscripción
            $suscripcion->update([
                'estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE'),
                'fin_periodo_prueba' => now()
            ]);

            // Enviar notificación
            $this->enviarNotificacionFinPrueba($suscripcion);

            Log::channel('suscripciones')->info("Período de prueba finalizado para suscripción {$suscripcion->id}");
        } catch (\Exception $e) {
            Log::channel('suscripciones')->error(
                "Error procesando fin de período de prueba {$suscripcion->id}: {$e->getMessage()}"
            );
        }
    }

    private function desactivarCuenta(Suscripcion $suscripcion)
    {
        try {
            // Actualizar suscripción
            $suscripcion->update([
                'estado' => config('constants.ESTADO_SUSCRIPCION_INACTIVO')
            ]);

            // Desactivar usuario
            if ($suscripcion->usuario) {
                $suscripcion->usuario->update(['enable' => false]);
            }

            // Enviar notificación de desactivación
            $this->enviarNotificacionVencimiento($suscripcion, 'desactivacion');

            Log::channel('suscripciones')->info("Cuenta desactivada para suscripción {$suscripcion->id}");
        } catch (\Exception $e) {
            Log::channel('suscripciones')->error(
                "Error desactivando cuenta {$suscripcion->id}: {$e->getMessage()}"
            );
        }
    }

    private function enviarNotificacionVencimiento(Suscripcion $suscripcion, string $tipo)
    {
        $usuario = $suscripcion->usuario;
        if (!$usuario) return;

        $mensajes = [
            'primera_alerta' => 'Tu suscripción ha vencido. Por favor, realiza el pago para mantener el servicio activo.',
            'alerta_critica' => '¡IMPORTANTE! Tu cuenta será desactivada pronto por falta de pago.',
            'desactivacion' => 'Tu cuenta ha sido desactivada por falta de pago. Contacta con soporte para reactivarla.'
        ];

        Log::channel('suscripciones')->info("Enviando notificación de vencimiento para suscripción {$suscripcion->id}: {$mensajes[$tipo]}");

        // $usuario->notify(new SuscripcionNotification(
        //     $mensajes[$tipo] ?? 'Tu suscripción requiere atención.',
        //     $tipo
        // ));
    }

    private function enviarNotificacionFinPrueba(Suscripcion $suscripcion)
    {
        $usuario = $suscripcion->usuario;
        if (!$usuario) return;

        Log::channel('suscripciones')->info("Enviando notificación de fin de prueba para suscripción {$suscripcion->id}: 'Tu período de prueba ha finalizado. Realiza el pago para continuar usando el servicio.'");

        // $usuario->notify(new SuscripcionNotification(
        //     'Tu período de prueba ha finalizado. Realiza el pago para continuar usando el servicio.',
        //     'fin_prueba'
        // ));
    }
}