<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VerificarSuscripcion extends Command
{
    protected $signature = 'suscripciones:verificar';
    protected $description = 'Verifica y actualiza el estado de las suscripciones, períodos de prueba y envía notificaciones';

    private const DIAS_PERIODO_PRUEBA = 3;

    public function handle()
    {
        $this->info('Iniciando verificación de suscripciones...');
        Log::channel('suscripciones')->info('Iniciando verificación de suscripciones');

        try {
            $this->verificarPeriodosPrueba();
            $this->verificarSuscripcionesVencidas();
            $this->procesarSuscripcionesCanceladas();

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

    private function procesarSuscripcionesCanceladas()
    {
        $this->info('Procesando suscripciones canceladas...');
        Log::channel('suscripciones')->info('Iniciando procesamiento de suscripciones canceladas');

        // Buscar suscripciones canceladas con fecha de próximo pago menor o igual a hoy
        $suscripcionesCanceladas = Suscripcion::where('estado', config('constants.ESTADO_SUSCRIPCION_CANCELADO'))
            ->where('fecha_proximo_pago', '<=', Carbon::now())
            ->get();

        $this->info('Suscripciones canceladas a procesar: ' . $suscripcionesCanceladas->count());

        foreach ($suscripcionesCanceladas as $suscripcion) {
            $this->procesarDesactivacionCancelada($suscripcion);
        }

        $this->info('Procesamiento de suscripciones canceladas completado');
        Log::channel('suscripciones')->info('Procesamiento de suscripciones canceladas completado');
    }

    private function procesarDesactivacionCancelada(Suscripcion $suscripcion)
    {
        try {
            $usuario = User::find($suscripcion->usuario_id);

            if (!$usuario) {
                $this->warn('Usuario no encontrado para suscripción cancelada ID: ' . $suscripcion->id);
                return;
            }

            // Desactivar usuario
            $usuario->enable = false;
            $usuario->save();

            $this->info('Usuario desactivado por cancelación: ' . $usuario->email);
            Log::channel('suscripciones')->info('Usuario desactivado por suscripción cancelada', [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
                'suscripcion_id' => $suscripcion->id
            ]);

            // Enviar notificación de desactivación
            // Comenta esta parte si no quieres enviar notificaciones por correo
            /*
            Mail::send('mails.notificacion_desactivacion', [
                'nombre' => $usuario->name,
                'empresa' => $usuario->empresa->nombre ?? 'su empresa'
            ], function ($m) use ($usuario) {
                $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                    ->to($usuario->email)
                    ->subject('Tu cuenta ha sido desactivada');
            });
            */

            // O usa tu sistema de notificaciones existente
            /*
            $usuario->notify(new SuscripcionNotification(
                'Tu cuenta ha sido desactivada después de cancelar tu suscripción.',
                'desactivacion_cancelada'
            ));
            */
        } catch (\Exception $e) {
            $this->error('Error al desactivar usuario para suscripción cancelada ID ' . $suscripcion->id . ': ' . $e->getMessage());
            Log::channel('suscripciones')->error('Error al desactivar usuario cancelado', [
                'suscripcion_id' => $suscripcion->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function manejarSuscripcionVencida(Suscripcion $suscripcion, int $diasVencidos)
    {
        $diasProrroga = max(1, (int) config('constants.DIAS_PRORROGA_SUSCRIPCION'));
        $inactivo = config('constants.ESTADO_SUSCRIPCION_INACTIVO');

        if ($suscripcion->estado === $inactivo) {
            return;
        }

        // Prórroga = N días con acceso; suspensión desde el día N+1 si siguen saldos pendientes con el sistema.
        if ($diasVencidos > $diasProrroga) {
            $this->desactivarCuenta($suscripcion);

            return;
        }

        // Prórroga corta (≤3 días): alertas días 1..N; sin pasar a Cancelado a mitad de gracia.
        if ($diasProrroga <= 3) {
            if ($diasVencidos >= 1 && $diasVencidos < 2) {
                $this->enviarNotificacionVencimiento($suscripcion, 'primera_alerta');
            } elseif ($diasVencidos >= 2 && $diasVencidos < 3) {
                $this->enviarNotificacionVencimiento($suscripcion, 'alerta_critica');
            } elseif ($diasVencidos >= 3 && $diasVencidos <= $diasProrroga) {
                $this->enviarNotificacionVencimiento($suscripcion, 'alerta_critica');
            }

            return;
        }

        $limitePrimera = max(1, (int) floor($diasProrroga / 3));

        if ($diasVencidos >= 1 && $diasVencidos < $limitePrimera + 1) {
            $this->enviarNotificacionVencimiento($suscripcion, 'primera_alerta');
        } elseif ($diasVencidos >= $limitePrimera + 1 && $diasVencidos < $diasProrroga - 1) {
            $this->enviarNotificacionVencimiento($suscripcion, 'alerta_critica');
        } elseif ($diasVencidos >= $diasProrroga - 1 && $diasVencidos < $diasProrroga) {
            $this->enviarNotificacionVencimiento($suscripcion, 'cancelado');
            $suscripcion->update(['estado' => config('constants.ESTADO_SUSCRIPCION_CANCELADO')]);
        } elseif ($diasVencidos === $diasProrroga) {
            // Último día de prórroga antes del bloqueo (día N+1 desactiva; p. ej. N=10 → día 10 aún en gracia).
            $this->enviarNotificacionVencimiento($suscripcion, 'alerta_critica');
        }
    }

    private function procesarFinPeriodoPrueba(Suscripcion $suscripcion)
    {
        $this->info("Procesando fin de período de prueba para suscripción ID: {$suscripcion->id}");

        try {
            // Actualizar estado de la suscripción
            $suscripcion->update([
                'estado' => config('constants.ESTADO_SUSCRIPCION_EN_PRUEBA'),
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
            // if ($suscripcion->usuario) {
            //     $suscripcion->usuario->update(['enable' => false]);
            // }

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
            'primera_alerta' => 'Tu suscripción requiere atención: hay saldos pendientes con el sistema. Regulariza tu situación para mantener el servicio activo.',
            'alerta_critica' => 'Importante: si persisten saldos pendientes con el sistema, tu acceso podría verse limitado pronto.',
            'desactivacion' => 'Tu cuenta está suspendida por saldos pendientes con el sistema. Contacta a soporte para regularizar tu situación.',
            'cancelado' => 'Tu suscripción fue cancelada por saldos pendientes con el sistema. Contacta a soporte para revisar opciones.'
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
