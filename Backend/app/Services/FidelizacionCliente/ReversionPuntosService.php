<?php

namespace App\Services\FidelizacionCliente;

use App\Models\Admin\Empresa;
use App\Models\FidelizacionClientes\ConsumoPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Revierte puntos ganados y restaura puntos canjeados ante anulación o devolución.
 */
class ReversionPuntosService
{
    /**
     * Anulación de venta: revierte 100% de puntos ganados y restaura 100% de canjeados.
     */
    public function revertirPorAnulacion(Venta $venta): void
    {
        try {
            DB::transaction(function () use ($venta) {
                $this->revertirPorAnulacionSync($venta);
            });
        } catch (\Throwable $e) {
            Log::error('Error revirtiendo puntos por anulación', [
                'venta_id' => $venta->id ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function revertirPorAnulacionSync(Venta $venta): void
    {
        if (!$venta->id || !$venta->id_cliente || !$venta->id_empresa) {
            return;
        }

        if (!$this->empresaTieneFidelizacion((int) $venta->id_empresa)) {
            return;
        }

        $ganados = (int) ($venta->puntos_ganados ?? 0);
        $canjeados = (int) ($venta->puntos_canjeados ?? 0);

        if ($ganados <= 0 && $canjeados <= 0) {
            return;
        }

        if ($ganados > 0) {
            $this->sincronizarAjusteGanancia(
                (int) $venta->id_cliente,
                (int) $venta->id_empresa,
                (int) $venta->id,
                $ganados,
                'ajuste_anulacion_ganancia_' . $venta->id,
                "Reversión de puntos ganados por anulación de venta #{$venta->id}",
                (float) $venta->total
            );
        }

        if ($canjeados > 0) {
            $this->sincronizarRestaurarCanje(
                (int) $venta->id_cliente,
                (int) $venta->id_empresa,
                (int) $venta->id,
                $canjeados,
                'ajuste_anulacion_canje_' . $venta->id,
                "Restauración de puntos canjeados por anulación de venta #{$venta->id}",
                (float) ($venta->descuento_puntos ?? 0)
            );
        }
    }

    /**
     * Devolución parcial/total: ajusta ganados y canjeados en proporción al monto.
     */
    public function syncPorDevolucion(Devolucion $devolucion): void
    {
        try {
            DB::transaction(function () use ($devolucion) {
                $this->syncPorDevolucionSync($devolucion);
            });
        } catch (\Throwable $e) {
            Log::error('Error sincronizando puntos por devolución', [
                'devolucion_id' => $devolucion->id ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function syncPorDevolucionSync(Devolucion $devolucion): void
    {
        if (!$devolucion->id) {
            return;
        }

        $keyGanancia = 'ajuste_devolucion_ganancia_' . $devolucion->id;
        $keyCanje = 'ajuste_devolucion_canje_' . $devolucion->id;

        // Migrar clave legada (ajuste único por monto) si existe
        $this->migrarAjusteLegadoDevolucion($devolucion);

        $targets = $this->calcularTargetsDevolucion($devolucion);

        if ($targets['ganancia'] <= 0) {
            $this->eliminarAjusteYReembolsarGanancia($keyGanancia);
        }

        if ($targets['canje'] <= 0) {
            $this->eliminarRestauracionCanje($keyCanje, (int) ($devolucion->id_venta ?? 0));
        }

        if (
            (!$devolucion->enable)
            || !in_array($devolucion->tipo, ['devolucion', 'descuento_ajuste'], true)
            || !$devolucion->id_cliente
            || !$devolucion->id_empresa
            || !$devolucion->id_venta
        ) {
            return;
        }

        if (!$this->empresaTieneFidelizacion((int) $devolucion->id_empresa)) {
            return;
        }

        if ($targets['ganancia'] > 0) {
            $this->sincronizarAjusteGanancia(
                (int) $devolucion->id_cliente,
                (int) $devolucion->id_empresa,
                (int) $devolucion->id_venta,
                $targets['ganancia'],
                $keyGanancia,
                "Ajuste de puntos ganados por devolución #{$devolucion->id} (venta #{$devolucion->id_venta})",
                (float) $devolucion->total
            );
        }

        if ($targets['canje'] > 0) {
            $this->sincronizarRestaurarCanje(
                (int) $devolucion->id_cliente,
                (int) $devolucion->id_empresa,
                (int) $devolucion->id_venta,
                $targets['canje'],
                $keyCanje,
                "Restauración de puntos canjeados por devolución #{$devolucion->id} (venta #{$devolucion->id_venta})",
                (float) $devolucion->total
            );
        }
    }

    /**
     * @return array{ganancia: int, canje: int}
     */
    private function calcularTargetsDevolucion(Devolucion $devolucion): array
    {
        if (
            !$devolucion->enable
            || !in_array($devolucion->tipo, ['devolucion', 'descuento_ajuste'], true)
            || !$devolucion->id_venta
        ) {
            return ['ganancia' => 0, 'canje' => 0];
        }

        $venta = Venta::find($devolucion->id_venta);
        if (!$venta) {
            return ['ganancia' => 0, 'canje' => 0];
        }

        $ventaTotal = (float) $venta->total;
        $devTotal = (float) $devolucion->total;
        if ($ventaTotal <= 0 || $devTotal <= 0) {
            return ['ganancia' => 0, 'canje' => 0];
        }

        $factor = min(1.0, $devTotal / $ventaTotal);
        $ganadosVenta = (int) ($venta->puntos_ganados ?? 0);
        $canjeadosVenta = (int) ($venta->puntos_canjeados ?? 0);

        $idealGanancia = (int) floor($ganadosVenta * $factor);
        $idealCanje = (int) floor($canjeadosVenta * $factor);

        $otrosGanancia = $this->sumaAjustesGananciaDevolucionesOtras((int) $venta->id, (int) $devolucion->id);
        $otrosCanje = $this->sumaRestauracionesCanjeDevolucionesOtras((int) $venta->id, (int) $devolucion->id);

        $capGanancia = max(0, $ganadosVenta - $otrosGanancia);
        $capCanje = max(0, $canjeadosVenta - $otrosCanje);

        return [
            'ganancia' => min($idealGanancia, $capGanancia),
            'canje' => min($idealCanje, $capCanje),
        ];
    }

    private function sumaAjustesGananciaDevolucionesOtras(int $ventaId, int $excluirDevolucionId): int
    {
        return (int) TransaccionPuntos::where('id_venta', $ventaId)
            ->where('tipo', TransaccionPuntos::TIPO_AJUSTE)
            ->where('idempotency_key', 'like', 'ajuste_devolucion_ganancia_%')
            ->where('idempotency_key', '!=', 'ajuste_devolucion_ganancia_' . $excluirDevolucionId)
            ->get()
            ->sum(function ($tx) {
                return abs((int) $tx->puntos);
            });
    }

    private function sumaRestauracionesCanjeDevolucionesOtras(int $ventaId, int $excluirDevolucionId): int
    {
        return (int) TransaccionPuntos::where('id_venta', $ventaId)
            ->where('tipo', TransaccionPuntos::TIPO_AJUSTE)
            ->where('idempotency_key', 'like', 'ajuste_devolucion_canje_%')
            ->where('idempotency_key', '!=', 'ajuste_devolucion_canje_' . $excluirDevolucionId)
            ->get()
            ->sum(function ($tx) {
                return max(0, (int) $tx->puntos);
            });
    }

    private function sincronizarAjusteGanancia(
        int $clienteId,
        int $empresaId,
        int $ventaId,
        int $targetAbs,
        string $idempotencyKey,
        string $descripcion,
        float $montoAsociado
    ): void {
        $existing = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
        $applied = $existing ? abs((int) $existing->puntos) : 0;

        $puntosCliente = PuntosCliente::where('id_cliente', $clienteId)
            ->where('id_empresa', $empresaId)
            ->lockForUpdate()
            ->first();

        if (!$puntosCliente) {
            $this->eliminarAjusteYReembolsarGanancia($idempotencyKey);
            return;
        }

        $disponibles = (int) $puntosCliente->puntos_disponibles;
        $cap = $applied + $disponibles;
        $newTarget = min($targetAbs, $cap);
        $delta = $newTarget - $applied;

        if ($delta === 0 && $existing && $newTarget > 0) {
            return;
        }

        $saldoAntes = $disponibles;

        if ($delta > 0) {
            $tgAntes = (int) $puntosCliente->puntos_totales_ganados;
            $puntosCliente->decrement('puntos_disponibles', $delta);
            $puntosCliente->update([
                'puntos_totales_ganados' => max(0, $tgAntes - $delta),
                'fecha_ultima_actividad' => now(),
            ]);
            $this->marcarGananciaVentaConsumida($ventaId, $delta);
        } elseif ($delta < 0) {
            $refund = abs($delta);
            $puntosCliente->increment('puntos_disponibles', $refund);
            $puntosCliente->increment('puntos_totales_ganados', $refund);
            $puntosCliente->update(['fecha_ultima_actividad' => now()]);
            $this->liberarGananciaVentaConsumida($ventaId, $refund);
        }

        $puntosCliente->refresh();
        $saldoDespues = (int) $puntosCliente->puntos_disponibles;
        $finalApplied = $applied + $delta;

        if ($finalApplied <= 0) {
            if ($existing) {
                $existing->delete();
            }
            return;
        }

        $payload = [
            'id_cliente' => $clienteId,
            'id_empresa' => $empresaId,
            'id_venta' => $ventaId,
            'tipo' => TransaccionPuntos::TIPO_AJUSTE,
            'puntos' => -$finalApplied,
            'puntos_antes' => $saldoAntes,
            'puntos_despues' => $saldoDespues,
            'monto_asociado' => $montoAsociado,
            'puntos_consumidos' => 0,
            'descripcion' => $descripcion,
            'fecha_expiracion' => null,
            'idempotency_key' => $idempotencyKey,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            TransaccionPuntos::create($payload);
        }
    }

    private function sincronizarRestaurarCanje(
        int $clienteId,
        int $empresaId,
        int $ventaId,
        int $targetRestore,
        string $idempotencyKey,
        string $descripcion,
        float $montoAsociado
    ): void {
        $existing = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
        $applied = $existing ? max(0, (int) $existing->puntos) : 0;
        $delta = $targetRestore - $applied;

        if ($delta === 0) {
            return;
        }

        $puntosCliente = PuntosCliente::where('id_cliente', $clienteId)
            ->where('id_empresa', $empresaId)
            ->lockForUpdate()
            ->first();

        if (!$puntosCliente) {
            $puntosCliente = PuntosCliente::create([
                'id_cliente' => $clienteId,
                'id_empresa' => $empresaId,
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'fecha_ultima_actividad' => now(),
            ]);
            $puntosCliente = PuntosCliente::where('id', $puntosCliente->id)->lockForUpdate()->first();
        }

        $saldoAntes = (int) $puntosCliente->puntos_disponibles;

        if ($delta > 0) {
            $this->deshacerFifoCanjeVenta($ventaId, $delta);
            $tcAntes = (int) $puntosCliente->puntos_totales_canjeados;
            $puntosCliente->increment('puntos_disponibles', $delta);
            $puntosCliente->update([
                'puntos_totales_canjeados' => max(0, $tcAntes - $delta),
                'fecha_ultima_actividad' => now(),
            ]);
        } else {
            $quitar = abs($delta);
            $this->reaplicarFifoCanjeVenta($ventaId, $quitar);
            $disponibles = (int) $puntosCliente->puntos_disponibles;
            $efectivo = min($quitar, $disponibles);
            if ($efectivo > 0) {
                $puntosCliente->decrement('puntos_disponibles', $efectivo);
                $puntosCliente->increment('puntos_totales_canjeados', $efectivo);
                $puntosCliente->update(['fecha_ultima_actividad' => now()]);
            }
            $delta = -$efectivo;
        }

        $puntosCliente->refresh();
        $saldoDespues = (int) $puntosCliente->puntos_disponibles;
        $finalApplied = $applied + $delta;

        if ($finalApplied <= 0) {
            if ($existing) {
                $existing->delete();
            }
            return;
        }

        $payload = [
            'id_cliente' => $clienteId,
            'id_empresa' => $empresaId,
            'id_venta' => $ventaId,
            'tipo' => TransaccionPuntos::TIPO_AJUSTE,
            'puntos' => $finalApplied,
            'puntos_antes' => $saldoAntes,
            'puntos_despues' => $saldoDespues,
            'monto_asociado' => $montoAsociado,
            'puntos_consumidos' => 0,
            'descripcion' => $descripcion,
            'fecha_expiracion' => null,
            'idempotency_key' => $idempotencyKey,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            TransaccionPuntos::create($payload);
        }
    }

    private function marcarGananciaVentaConsumida(int $ventaId, int $puntos): void
    {
        if ($puntos <= 0) {
            return;
        }

        $ganancia = TransaccionPuntos::where('id_venta', $ventaId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->lockForUpdate()
            ->first();

        if (!$ganancia) {
            return;
        }

        $disponibleEnGanancia = max(0, (int) $ganancia->puntos - (int) $ganancia->puntos_consumidos);
        $aMarcar = min($puntos, $disponibleEnGanancia);
        if ($aMarcar > 0) {
            $ganancia->increment('puntos_consumidos', $aMarcar);
        }
    }

    private function liberarGananciaVentaConsumida(int $ventaId, int $puntos): void
    {
        if ($puntos <= 0) {
            return;
        }

        $ganancia = TransaccionPuntos::where('id_venta', $ventaId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->lockForUpdate()
            ->first();

        if (!$ganancia) {
            return;
        }

        $aLiberar = min($puntos, (int) $ganancia->puntos_consumidos);
        if ($aLiberar > 0) {
            $ganancia->decrement('puntos_consumidos', $aLiberar);
        }
    }

    private function findCanjeVenta(int $ventaId): ?TransaccionPuntos
    {
        return TransaccionPuntos::where('tipo', TransaccionPuntos::TIPO_CANJE)
            ->where('descripcion', 'like', "%venta #{$ventaId}%")
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function deshacerFifoCanjeVenta(int $ventaId, int $puntos): void
    {
        if ($puntos <= 0) {
            return;
        }

        $canje = $this->findCanjeVenta($ventaId);
        if (!$canje) {
            return;
        }

        $restantes = $puntos;
        $consumos = ConsumoPuntos::where('id_canje_tx', $canje->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($consumos as $consumo) {
            if ($restantes <= 0) {
                break;
            }

            $enConsumo = (int) $consumo->puntos_consumidos;
            if ($enConsumo <= 0) {
                continue;
            }

            $liberar = min($restantes, $enConsumo);
            $ganancia = TransaccionPuntos::where('id', $consumo->id_ganancia_tx)->lockForUpdate()->first();
            if ($ganancia) {
                $ganancia->decrement('puntos_consumidos', min($liberar, (int) $ganancia->puntos_consumidos));
            }

            if ($liberar >= $enConsumo) {
                $consumo->delete();
            } else {
                $consumo->decrement('puntos_consumidos', $liberar);
            }

            $restantes -= $liberar;
        }
    }

    private function reaplicarFifoCanjeVenta(int $ventaId, int $puntos): void
    {
        if ($puntos <= 0) {
            return;
        }

        $canje = $this->findCanjeVenta($ventaId);
        if (!$canje) {
            return;
        }

        $restantes = $puntos;
        $ganancias = TransaccionPuntos::where('id_cliente', $canje->id_cliente)
            ->where('id_empresa', $canje->id_empresa)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->whereRaw('puntos - puntos_consumidos > 0')
            ->orderBy('fecha_expiracion', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($ganancias as $ganancia) {
            if ($restantes <= 0) {
                break;
            }

            $disponible = max(0, (int) $ganancia->puntos - (int) $ganancia->puntos_consumidos);
            $tomar = min($restantes, $disponible);
            if ($tomar <= 0) {
                continue;
            }

            $ganancia->increment('puntos_consumidos', $tomar);
            ConsumoPuntos::create([
                'id_empresa' => $canje->id_empresa,
                'id_cliente' => $canje->id_cliente,
                'id_canje_tx' => $canje->id,
                'id_ganancia_tx' => $ganancia->id,
                'puntos_consumidos' => $tomar,
                'descripcion' => 'Reaplicación FIFO tras ajuste de devolución',
            ]);
            $restantes -= $tomar;
        }
    }

    private function eliminarAjusteYReembolsarGanancia(string $idempotencyKey): void
    {
        $existing = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
        if (!$existing) {
            return;
        }

        $reintegro = abs((int) $existing->puntos);
        $ventaId = (int) ($existing->id_venta ?? 0);
        $idCliente = $existing->id_cliente;
        $idEmpresa = $existing->id_empresa;
        $existing->delete();

        if ($reintegro <= 0 || !$idCliente || !$idEmpresa) {
            return;
        }

        $pc = PuntosCliente::where('id_cliente', $idCliente)
            ->where('id_empresa', $idEmpresa)
            ->lockForUpdate()
            ->first();

        if (!$pc) {
            return;
        }

        $pc->increment('puntos_disponibles', $reintegro);
        $pc->increment('puntos_totales_ganados', $reintegro);
        $pc->update(['fecha_ultima_actividad' => now()]);

        if ($ventaId > 0) {
            $this->liberarGananciaVentaConsumida($ventaId, $reintegro);
        }
    }

    private function eliminarRestauracionCanje(string $idempotencyKey, int $ventaId): void
    {
        $existing = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
        if (!$existing) {
            return;
        }

        $quitar = max(0, (int) $existing->puntos);
        $idCliente = $existing->id_cliente;
        $idEmpresa = $existing->id_empresa;
        $existing->delete();

        if ($quitar <= 0 || !$idCliente || !$idEmpresa) {
            return;
        }

        $pc = PuntosCliente::where('id_cliente', $idCliente)
            ->where('id_empresa', $idEmpresa)
            ->lockForUpdate()
            ->first();

        if ($pc) {
            $efectivo = min($quitar, (int) $pc->puntos_disponibles);
            if ($efectivo > 0) {
                $pc->decrement('puntos_disponibles', $efectivo);
                $pc->increment('puntos_totales_canjeados', $efectivo);
                $pc->update(['fecha_ultima_actividad' => now()]);
            }
        }

        if ($ventaId > 0) {
            $this->reaplicarFifoCanjeVenta($ventaId, $quitar);
        }
    }

    private function empresaTieneFidelizacion(int $empresaId): bool
    {
        $empresa = Empresa::find($empresaId);

        return $empresa && $empresa->tieneFidelizacionHabilitada();
    }

    /**
     * Elimina el ajuste legado `ajuste_devolucion_{id}` (cálculo por monto) y reembolsa esos puntos,
     * para que el nuevo sync proporcional no acumule dobles descuentos.
     */
    private function migrarAjusteLegadoDevolucion(Devolucion $devolucion): void
    {
        $legacyKey = 'ajuste_devolucion_' . $devolucion->id;
        $existing = TransaccionPuntos::where('idempotency_key', $legacyKey)->lockForUpdate()->first();
        if (!$existing) {
            return;
        }

        $reintegro = abs((int) $existing->puntos);
        $idCliente = $existing->id_cliente;
        $idEmpresa = $existing->id_empresa;
        $existing->delete();

        if ($reintegro <= 0 || !$idCliente || !$idEmpresa) {
            return;
        }

        $pc = PuntosCliente::where('id_cliente', $idCliente)
            ->where('id_empresa', $idEmpresa)
            ->lockForUpdate()
            ->first();

        if (!$pc) {
            return;
        }

        $pc->increment('puntos_disponibles', $reintegro);
        $pc->increment('puntos_totales_ganados', $reintegro);
        $pc->update(['fecha_ultima_actividad' => now()]);
    }
}
