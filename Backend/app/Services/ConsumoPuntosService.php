<?php

namespace App\Services;

use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Services\NotificacionPuntosService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ConsumoPuntosService
{
    /**
     * Procesar la acumulación de puntos para una venta
     *
     * @param Venta $venta
     * @return bool
     */
    public function procesarAcumulacionPuntos(Venta $venta): bool
    {
        // Evitar procesar en contextos de testing o comandos específicos
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $command = request()->server('argv.1') ?? '';
            if (in_array($command, ['migrate', 'db:seed', 'queue:work', 'schedule:run'])) {
                return false;
            }
        }

        // Procesar solo si la venta está confirmada/completada
        if (in_array($venta->estado, ['Borrador', 'Cancelada', 'Anulada'])) {
            return false;
        }

        try {
            // Verificar si el sistema está bajo alta carga
            if ($this->shouldProcessAsync()) {
                $this->procesarPuntosAsync($venta);
                return true;
            } else {
                return $this->procesarAcumulacionPuntosSync($venta);
            }
        } catch (\Exception $e) {
            // Log del error pero no interrumpir la venta
            Log::error('Error al procesar acumulación de puntos para venta ID: ' . $venta->id, [
                'error' => $e->getMessage(),
                'venta_id' => $venta->id,
                'cliente_id' => $venta->id_cliente,
                'empresa_id' => $venta->id_empresa,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Procesar la acumulación de puntos de forma síncrona
     *
     * @param Venta $venta
     * @return bool
     */
    private function procesarAcumulacionPuntosSync(Venta $venta): bool
    {
        // 1. Verificaciones básicas rápidas primero
        if (!$venta->id_cliente) {
            Log::debug('Venta sin cliente asignado, no se generan puntos', ['venta_id' => $venta->id]);
            return false;
        }

        // 2. Verificar que no se hayan generado puntos previamente (optimizado)
        if ($venta->puntos_ganados > 0) {
            Log::debug('Venta ya tiene puntos generados', ['venta_id' => $venta->id]);
            return false;
        }

        // 3. Verificar si ya existe una transacción de puntos para esta venta (más eficiente)
        $existeTransaccion = TransaccionPuntos::where('id_venta', $venta->id)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->exists();
            
        if ($existeTransaccion) {
            Log::debug('Ya existe transacción de puntos para esta venta', ['venta_id' => $venta->id]);
            return false;
        }

        // 4. Verificar si la empresa tiene fidelización habilitada (con cache)
        $empresaId = $venta->id_empresa;
        Log::info('Empresa ID: ' . $empresaId);
        Log::info("Iniciando verificación de fidelización para empresa", ['empresa_id' => $empresaId]);

        $tieneFidelizacion = $this->verificarFidelizacionHabilitada($empresaId);

        Log::info("Resultado final de tieneFidelizacion", [
            'empresa_id' => $empresaId,
            'tieneFidelizacion' => $tieneFidelizacion
        ]);

        if (!$tieneFidelizacion) {
            Log::debug('Empresa no tiene fidelización habilitada', ['empresa_id' => $empresaId]);
            return false;
        }

        // 5. Obtener el cliente con su tipo de cliente (eager loading)
        $cliente = $venta->cliente()->with('tipoCliente.tipoBase')->first();
        if (!$cliente) {
            Log::warning('Cliente no encontrado para venta', ['venta_id' => $venta->id, 'cliente_id' => $venta->id_cliente]);
            return false;
        }

        // 6. Obtener tipo de cliente efectivo (optimizado)
        $tipoCliente = $cliente->tipoCliente;
        if (!$tipoCliente) {
            // Obtener tipo por defecto de la empresa (con cache)
            $tipoCliente = cache()->remember(
                "empresa_tipo_default_{$empresaId}",
                now()->addMinutes(60),
                function () use ($empresaId) {
                    return TipoClienteEmpresa::where('id_empresa', $empresaId)
                        ->where('is_default', true)
                        ->with('tipoBase')
                        ->first();
                }
            );
        }

        if (!$tipoCliente) {
            Log::warning('No se pudo determinar tipo de cliente efectivo', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresaId
            ]);
            return false;
        }

        // 7. Calcular puntos basado en el monto total de la venta
        $puntosCalculados = $tipoCliente->calcularPuntos($venta->total);
        
        if ($puntosCalculados <= 0) {
            Log::debug('No se generan puntos para esta venta (puntos calculados: 0)', [
                'venta_id' => $venta->id,
                'monto' => $venta->total,
                'puntos_por_dolar' => $tipoCliente->puntos_por_dolar
            ]);
            return false;
        }

        // 8. Procesar la acumulación en una transacción de base de datos
        try {
            DB::transaction(function () use ($venta, $cliente, $tipoCliente, $puntosCalculados) {
                $this->crearTransaccionPuntos($venta, $cliente, $tipoCliente, $puntosCalculados);
            });

            Log::info('Puntos acumulados exitosamente', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'puntos_generados' => $puntosCalculados,
                'tipo_cliente' => $tipoCliente->nombre_efectivo
            ]);

            // Enviar notificación por email al cliente
            $this->enviarNotificacionPuntos($venta, $puntosCalculados);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al crear transacción de puntos', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Crear la transacción de puntos y actualizar saldos
     *
     * @param Venta $venta
     * @param \App\Models\Ventas\Clientes\Cliente $cliente
     * @param TipoClienteEmpresa $tipoCliente
     * @param int $puntosCalculados
     * @return void
     */
    private function crearTransaccionPuntos($venta, $cliente, $tipoCliente, $puntosCalculados)
    {
        // Obtener o crear registro de puntos del cliente (optimizado)
        $puntosCliente = PuntosCliente::where('id_cliente', $cliente->id)
            ->where('id_empresa', $venta->id_empresa)
            ->first();

        if (!$puntosCliente) {
            $puntosCliente = PuntosCliente::create([
                'id_cliente' => $cliente->id,
                'id_empresa' => $venta->id_empresa,
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'fecha_ultima_actividad' => now()
            ]);
        }

        $puntosAntes = $puntosCliente->puntos_disponibles;
        $puntosDespues = $puntosAntes + $puntosCalculados;

        // Generar clave de idempotencia
        $idempotencyKey = TransaccionPuntos::generarIdempotencyKey(
            $cliente->id,
            TransaccionPuntos::TIPO_GANANCIA,
            $venta->id
        );

        // Verificar duplicados por idempotencia antes de crear
        $transaccionExistente = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->first();
        if ($transaccionExistente) {
            Log::warning('Transacción duplicada detectada por idempotencia', [
                'venta_id' => $venta->id,
                'idempotency_key' => $idempotencyKey
            ]);
            return;
        }

        // Crear transacción de puntos
        $transaccion = TransaccionPuntos::create([
            'id_cliente' => $cliente->id,
            'id_empresa' => $venta->id_empresa,
            'id_venta' => $venta->id,
            'tipo' => TransaccionPuntos::TIPO_GANANCIA,
            'puntos' => $puntosCalculados,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'monto_asociado' => $venta->total,
            'puntos_consumidos' => 0,
            'descripcion' => "Puntos ganados por venta #{$venta->id}",
            'fecha_expiracion' => $tipoCliente->getFechaExpiracion(),
            'idempotency_key' => $idempotencyKey
        ]);

        // Actualizar saldo consolidado en puntos_cliente (usando update directo para mejor rendimiento)
        $puntosCliente->increment('puntos_disponibles', $puntosCalculados);
        $puntosCliente->increment('puntos_totales_ganados', $puntosCalculados);
        $puntosCliente->update(['fecha_ultima_actividad' => now()]);

        // Actualizar campo puntos_ganados en la venta (usando update directo)
        $venta->update(['puntos_ganados' => $puntosCalculados]);

        Log::info('Transacción de puntos creada', [
            'transaccion_id' => $transaccion->id,
            'venta_id' => $venta->id,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'fecha_expiracion' => $transaccion->fecha_expiracion
        ]);
    }

    /**
     * Verificar si la empresa tiene fidelización habilitada
     *
     * @param int $empresaId
     * @return bool
     */
    private function verificarFidelizacionHabilitada(int $empresaId): bool
    {
        return cache()->remember(
            "empresa_fidelizacion_{$empresaId}",
            now()->addMinutes(30),
            function () use ($empresaId) {
                $empresa = \App\Models\Admin\Empresa::find($empresaId);
                return $empresa && $empresa->tieneFidelizacionHabilitada();
            }
        );
    }

    /**
     * Determinar si se debe procesar de forma asíncrona
     *
     * @return bool
     */
    private function shouldProcessAsync(): bool
    {
        // Verificar carga del sistema
        $loadAverage = sys_getloadavg()[0] ?? 0;
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        
        // Procesar async si la carga es alta o el uso de memoria es alto
        return $loadAverage > 2.0 || $memoryUsage > 512;
    }

    /**
     * Procesar puntos de forma asíncrona
     *
     * @param Venta $venta
     * @return void
     */
    private function procesarPuntosAsync(Venta $venta)
    {
        // Aquí se podría implementar un job para procesar en cola
        // Por ahora, procesamos de forma síncrona pero con logging
        Log::info('Procesando puntos de forma asíncrona para venta', ['venta_id' => $venta->id]);
        $this->procesarAcumulacionPuntosSync($venta);
    }

    /**
     * Enviar notificación de puntos ganados por email
     *
     * @param Venta $venta
     * @param int $puntosGanados
     * @return void
     */
    private function enviarNotificacionPuntos(Venta $venta, int $puntosGanados): void
    {
        try {
            $notificacionService = new NotificacionPuntosService();
            
            // Verificar si se debe enviar notificación
            if ($notificacionService->debeEnviarNotificacion($venta->id_empresa)) {
                $notificacionService->enviarNotificacionPuntosGanados($venta, $puntosGanados);
            }
        } catch (\Exception $e) {
            // Log del error pero no interrumpir el proceso de puntos
            Log::error('Error al enviar notificación de puntos', [
                'venta_id' => $venta->id,
                'puntos_ganados' => $puntosGanados,
                'error' => $e->getMessage()
            ]);
        }
    }
}
