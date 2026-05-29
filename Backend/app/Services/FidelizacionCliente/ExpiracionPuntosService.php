<?php

namespace App\Services\FidelizacionCliente;

use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpiracionPuntosService
{
    /**
     * Procesar todas las ganancias de puntos que han expirado
     *
     * @return array{procesadas: int, puntos_vencidos: int, errores: array}
     */
    public function procesarExpiraciones(): array
    {
        $resultado = [
            'procesadas' => 0,
            'puntos_vencidos' => 0,
            'errores' => []
        ];

        $gananciasExpiradas = TransaccionPuntos::where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->where('fecha_expiracion', '<', now()->toDateString())
            ->whereRaw('puntos_consumidos < puntos')
            ->get();

        foreach ($gananciasExpiradas as $ganancia) {
            try {
                $puntosVencidos = $this->procesarGananciaExpirada($ganancia);
                $resultado['procesadas']++;
                $resultado['puntos_vencidos'] += $puntosVencidos;
            } catch (\Exception $e) {
                Log::error('Error al procesar expiración de puntos', [
                    'ganancia_id' => $ganancia->id,
                    'cliente_id' => $ganancia->id_cliente,
                    'empresa_id' => $ganancia->id_empresa,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $resultado['errores'][] = [
                    'ganancia_id' => $ganancia->id,
                    'mensaje' => $e->getMessage()
                ];
            }
        }

        return $resultado;
    }

    /**
     * Procesar la expiración de una ganancia específica
     *
     * @param TransaccionPuntos $ganancia
     * @return int Puntos vencidos
     */
    public function procesarGananciaExpirada(TransaccionPuntos $ganancia): int
    {
        return DB::transaction(function () use ($ganancia) {
            // Bloquear la fila para evitar procesamiento concurrente
            $gananciaLocked = TransaccionPuntos::where('id', $ganancia->id)
                ->lockForUpdate()
                ->first();

            if (!$gananciaLocked || $gananciaLocked->puntos_consumidos >= $gananciaLocked->puntos) {
                return 0; // Ya procesada por otro job
            }

            $puntosPorVencer = (int) ($gananciaLocked->puntos - $gananciaLocked->puntos_consumidos);
            if ($puntosPorVencer <= 0) {
                return 0;
            }

            // 1. Marcar la ganancia como completamente consumida (evita reprocesamiento)
            $gananciaLocked->update(['puntos_consumidos' => $gananciaLocked->puntos]);

            // 2. Obtener o crear registro de puntos del cliente
            $puntosCliente = PuntosCliente::firstOrCreate(
                [
                    'id_cliente' => $gananciaLocked->id_cliente,
                    'id_empresa' => $gananciaLocked->id_empresa
                ],
                [
                    'puntos_disponibles' => 0,
                    'puntos_totales_ganados' => 0,
                    'puntos_totales_canjeados' => 0,
                    'fecha_ultima_actividad' => now()
                ]
            );

            $puntosAntes = (int) $puntosCliente->puntos_disponibles;
            $puntosDespues = max(0, $puntosAntes - $puntosPorVencer);
            $puntosRealesVencidos = $puntosAntes - $puntosDespues;

            if ($puntosRealesVencidos <= 0) {
                return 0;
            }

            // 3. Crear transacción de expiración (puntos negativos)
            $idempotencyKey = TransaccionPuntos::generarIdempotencyKey(
                $gananciaLocked->id_cliente,
                TransaccionPuntos::TIPO_EXPIRACION,
                'ganancia_' . $gananciaLocked->id
            );

            $existeExpiracion = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->exists();
            if ($existeExpiracion) {
                Log::warning('Expiración duplicada detectada por idempotencia', [
                    'ganancia_id' => $gananciaLocked->id,
                    'idempotency_key' => $idempotencyKey
                ]);
                return 0;
            }

            TransaccionPuntos::create([
                'id_cliente' => $gananciaLocked->id_cliente,
                'id_empresa' => $gananciaLocked->id_empresa,
                'id_venta' => null,
                'tipo' => TransaccionPuntos::TIPO_EXPIRACION,
                'puntos' => -$puntosRealesVencidos,
                'puntos_antes' => $puntosAntes,
                'puntos_despues' => $puntosDespues,
                'monto_asociado' => null,
                'puntos_consumidos' => 0,
                'descripcion' => "Puntos vencidos por expiración (ganancia #{$gananciaLocked->id})",
                'fecha_expiracion' => null,
                'idempotency_key' => $idempotencyKey
            ]);

            // 4. Actualizar saldo del cliente
            $puntosCliente->decrement('puntos_disponibles', $puntosRealesVencidos);
            $puntosCliente->update(['fecha_ultima_actividad' => now()]);

            Log::info('Puntos expirados procesados', [
                'ganancia_id' => $gananciaLocked->id,
                'cliente_id' => $gananciaLocked->id_cliente,
                'empresa_id' => $gananciaLocked->id_empresa,
                'puntos_vencidos' => $puntosRealesVencidos,
                'fecha_expiracion_original' => $gananciaLocked->fecha_expiracion
            ]);

            return $puntosRealesVencidos;
        });
    }
}
