<?php

namespace App\Services\Suscripcion;

use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CancelacionSuscripcionService
{
    /**
     * Cancela una suscripción
     *
     * @param int $usuarioId
     * @param string $password
     * @param int $empresaId
     * @param string|null $motivoCancelacion
     * @return array
     * @throws \Exception
     */
    public function cancelarSuscripcion(int $usuarioId, string $password, int $empresaId, ?string $motivoCancelacion = null): array
    {
        // Verificar contraseña
        $usuario = User::findOrFail($usuarioId);
        if (!Hash::check($password, $usuario->password)) {
            throw new \Exception('La contraseña ingresada no es correcta', 422);
        }

        DB::beginTransaction();
        try {
            // Actualizar suscripción
            $suscripcion = Suscripcion::where('usuario_id', $usuarioId)
                ->where('estado', '!=', config('constants.ESTADO_SUSCRIPCION_CANCELADO'))
                ->latest()
                ->first();

            if (!$suscripcion) {
                throw new \Exception('No se encontró una suscripción activa para cancelar', 404);
            }

            // Obtener la fecha de fin del período actual
            $fechaFinPeriodo = Carbon::parse($suscripcion->fecha_proximo_pago);

            $suscripcion->estado = config('constants.ESTADO_SUSCRIPCION_CANCELADO');
            $suscripcion->motivo_cancelacion = $motivoCancelacion;
            $suscripcion->fecha_cancelacion = now();
            $suscripcion->save();

            // Actualizar empresa
            $empresa = Empresa::findOrFail($empresaId);
            $empresa->fecha_cancelacion = now();
            $empresa->save();

            Log::info('Suscripción cancelada', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresa->id,
                'fecha_desactivacion_programada' => $fechaFinPeriodo
            ]);

            // Enviar notificaciones
            $this->enviarNotificacionesCancelacion($usuario, $empresa, $fechaFinPeriodo, $motivoCancelacion);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Tu suscripción ha sido cancelada. Podrás seguir usando el sistema hasta ' . $fechaFinPeriodo->format('d/m/Y'),
                'fecha_desactivacion' => $fechaFinPeriodo->format('Y-m-d')
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Envía notificaciones de cancelación
     *
     * @param User $usuario
     * @param Empresa $empresa
     * @param Carbon $fechaDesactivacion
     * @param string|null $motivo
     * @return void
     */
    private function enviarNotificacionesCancelacion(User $usuario, Empresa $empresa, Carbon $fechaDesactivacion, ?string $motivo): void
    {
        // Notificar al administrador
        $dataAdmin = [
            'titulo' => 'Cancelación de Suscripción',
            'descripcion' => 'El usuario ' . $usuario->name . ' de la empresa ' . $empresa->nombre . ' ha cancelado su suscripción.',
            'motivo' => $motivo,
            'fecha_desactivacion' => $fechaDesactivacion->format('d/m/Y')
        ];

        Mail::send('mails.notificacion_cancelacion_admin', ['data' => $dataAdmin], function ($m) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                ->to(env('MAIL_TO_ADDRESS'))
                ->cc(config('constants.MAIL_CC_ADDRESS_1'))
                ->cc(config('constants.MAIL_CC_ADDRESS_2'))
                ->subject('Cancelación de suscripción SmartPyme');
        });

        // Notificar al usuario
        $dataUsuario = [
            'nombre' => $usuario->name,
            'empresa' => $empresa->nombre,
            'fecha_desactivacion' => $fechaDesactivacion->format('d/m/Y')
        ];

        Mail::send('mails.notificacion_cancelacion_usuario', ['data' => $dataUsuario], function ($m) use ($usuario) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                ->to($usuario->email)
                ->subject('Confirmación de cancelación de suscripción');
        });
    }
}
