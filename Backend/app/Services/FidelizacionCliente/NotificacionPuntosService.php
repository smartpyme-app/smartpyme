<?php

namespace App\Services\FidelizacionCliente;

use App\Mail\PuntosGanadosMailable;
use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\PuntosCliente;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificacionPuntosService
{
    /**
     * Enviar notificación de puntos ganados por email
     *
     * @param Venta $venta
     * @param int $puntosGanados
     * @return bool
     */
    public function enviarNotificacionPuntosGanados(Venta $venta, int $puntosGanados): bool
    {
        try {
            // Verificar que la venta tenga cliente asignado
            if (!$venta->id_cliente) {
                Log::warning('No se puede enviar notificación: venta sin cliente', [
                    'venta_id' => $venta->id
                ]);
                return false;
            }

            // Obtener el cliente
            $cliente = $venta->cliente;
            if (!$cliente) {
                Log::warning('No se puede enviar notificación: cliente no encontrado', [
                    'venta_id' => $venta->id,
                    'cliente_id' => $venta->id_cliente
                ]);
                return false;
            }

            // Verificar que el cliente tenga email
            if (!$cliente->correo || !filter_var($cliente->correo, FILTER_VALIDATE_EMAIL)) {
                Log::info('No se puede enviar notificación: cliente sin email válido', [
                    'venta_id' => $venta->id,
                    'cliente_id' => $cliente->id,
                    'email' => $cliente->correo
                ]);
                return false;
            }

            // Obtener el saldo actual de puntos del cliente
            $puntosCliente = PuntosCliente::where('id_cliente', $cliente->id)
                ->where('id_empresa', $venta->id_empresa)
                ->first();

            $puntosDisponibles = $puntosCliente ? $puntosCliente->puntos_disponibles : 0;

            // Obtener información de la empresa
            $empresa = $venta->empresa;

            // Preparar datos para el email
            $datosEmail = [
                'cliente' => $cliente,
                'empresa' => $empresa,
                'venta' => $venta,
                'puntos_ganados' => $puntosGanados,
                'puntos_disponibles' => $puntosDisponibles,
                'fecha_venta' => $venta->created_at,
                'numero_venta' => $venta->id
            ];

            // Enviar el email con manejo robusto de errores SSL
            $this->enviarEmailConManejoSSL($cliente->correo, $datosEmail);

            Log::info('Notificación de puntos enviada exitosamente', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'email' => $cliente->correo,
                'puntos_ganados' => $puntosGanados,
                'puntos_disponibles' => $puntosDisponibles
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error al enviar notificación de puntos', [
                'venta_id' => $venta->id,
                'cliente_id' => $venta->id_cliente,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Verificar si se debe enviar notificación para una empresa
     *
     * @param int $empresaId
     * @return bool
     */
    public function debeEnviarNotificacion(int $empresaId): bool
    {
        // Verificar si la empresa tiene fidelización habilitada
        $empresa = \App\Models\Admin\Empresa::find($empresaId);
        if (!$empresa || !$empresa->tieneFidelizacionHabilitada()) {
            return false;
        }
        return true;
    }

    /**
     * Enviar notificación de forma asíncrona
     *
     * @param Venta $venta
     * @param int $puntosGanados
     * @return void
     */
    public function enviarNotificacionAsync(Venta $venta, int $puntosGanados): void
    {
        $this->enviarNotificacionPuntosGanados($venta, $puntosGanados);
    }

    /**
     * Enviar email con manejo robusto de errores SSL
     *
     * @param string $email
     * @param array $datosEmail
     * @return void
     */
    private function enviarEmailConManejoSSL(string $email, array $datosEmail): void
    {
        try {
            // Configurar SSL permisivo para evitar errores de certificado
            stream_context_set_default([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'disable_compression' => true,
                    'ciphers' => 'DEFAULT@SECLEVEL=0'
                ]
            ]);

            // Enviar email real (tanto en desarrollo como en producción)
            Mail::to($email)->send(new PuntosGanadosMailable($datosEmail));
            Log::info("Email enviado exitosamente");
            
        } catch (\Exception $e) {
            Log::error("Error al enviar email", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

}
