<?php

namespace App\Services\FidelizacionCliente;

use App\Models\Admin\Empresa;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Devoluciones\Devolucion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resta puntos por devoluciones de venta usando la misma regla que las ventas:
 * floor(total * puntos_por_dolar) según el tipo de cliente efectivo del cliente.
 */
class DevolucionPuntosService
{
    public function __construct(
        private ConsumoPuntosService $consumoPuntosService
    ) {
    }

    /**
     * Sincroniza el ajuste de puntos asociado a una devolución (crear / actualizar / revertir).
     */
    public function syncPuntosParaDevolucion(Devolucion $devolucion): void
    {
        try {
            DB::transaction(function () use ($devolucion) {
                $this->syncPuntosParaDevolucionSync($devolucion);
            });
        } catch (\Throwable $e) {
            Log::error('Error sincronizando puntos por devolución', [
                'devolucion_id' => $devolucion->id ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function syncPuntosParaDevolucionSync(Devolucion $devolucion): void
    {
        if (!$devolucion->id) {
            return;
        }

        $key = $this->idempotencyKey((int) $devolucion->id);
        $existing = TransaccionPuntos::where('idempotency_key', $key)->lockForUpdate()->first();

        $calculated = $this->calcularPuntosTeoricosDevolucion($devolucion);

        if ($calculated <= 0) {
            $this->eliminarAjusteYReembolsar($existing);
            return;
        }

        if (!$devolucion->id_cliente || !$devolucion->id_empresa) {
            $this->eliminarAjusteYReembolsar($existing);
            return;
        }

        $puntosCliente = PuntosCliente::where('id_cliente', $devolucion->id_cliente)
            ->where('id_empresa', $devolucion->id_empresa)
            ->lockForUpdate()
            ->first();

        if (!$puntosCliente) {
            $this->eliminarAjusteYReembolsar($existing);
            return;
        }

        $applied = $existing ? abs((int) $existing->puntos) : 0;
        $disponibles = (int) $puntosCliente->puntos_disponibles;

        $cap = $applied + $disponibles;
        $newTarget = min($calculated, $cap);
        $delta = $newTarget - $applied;

        if ($delta === 0) {
            return;
        }

        $saldoAntes = (int) $puntosCliente->puntos_disponibles;

        if ($delta > 0) {
            $tgAntes = (int) $puntosCliente->puntos_totales_ganados;
            $puntosCliente->decrement('puntos_disponibles', $delta);
            $puntosCliente->update([
                'puntos_totales_ganados' => max(0, $tgAntes - $delta),
                'fecha_ultima_actividad' => now(),
            ]);
        } else {
            $refund = abs($delta);
            $puntosCliente->increment('puntos_disponibles', $refund);
            $puntosCliente->increment('puntos_totales_ganados', $refund);
            $puntosCliente->update(['fecha_ultima_actividad' => now()]);
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

        $descripcion = $this->buildDescripcion($devolucion);

        $payload = [
            'id_cliente' => (int) $devolucion->id_cliente,
            'id_empresa' => (int) $devolucion->id_empresa,
            'id_venta' => $devolucion->id_venta,
            'tipo' => TransaccionPuntos::TIPO_AJUSTE,
            'puntos' => -$finalApplied,
            'puntos_antes' => $saldoAntes,
            'puntos_despues' => $saldoDespues,
            'monto_asociado' => $devolucion->total,
            'puntos_consumidos' => 0,
            'descripcion' => $descripcion,
            'fecha_expiracion' => null,
            'idempotency_key' => $key,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            TransaccionPuntos::create($payload);
        }
    }

    /**
     * Puntos a descontar según total de la devolución y tipo de cliente (misma lógica que venta).
     */
    private function calcularPuntosTeoricosDevolucion(Devolucion $devolucion): int
    {
        if (!$devolucion->enable) {
            return 0;
        }

        if (!in_array($devolucion->tipo, ['devolucion', 'descuento_ajuste'], true)) {
            return 0;
        }

        $total = (float) $devolucion->total;
        if ($total <= 0 || !$devolucion->id_cliente || !$devolucion->id_empresa) {
            return 0;
        }

        $cliente = Cliente::with(['tipoCliente.tipoBase', 'empresa'])->find($devolucion->id_cliente);
        if (!$cliente) {
            return 0;
        }

        $empresa = Empresa::find($devolucion->id_empresa);
        if (!$empresa || !$empresa->tieneFidelizacionHabilitada()) {
            return 0;
        }

        $tipoCliente = $this->consumoPuntosService->obtenerTipoClienteEfectivo($cliente);
        if (!$tipoCliente) {
            return 0;
        }

        return max(0, $tipoCliente->calcularPuntos($total));
    }

    private function idempotencyKey(int $devolucionId): string
    {
        return 'ajuste_devolucion_' . $devolucionId;
    }

    private function buildDescripcion(Devolucion $devolucion): string
    {
        $refVenta = $devolucion->id_venta ? "#{$devolucion->id_venta}" : 'N/A';

        return "Ajuste por devolución #{$devolucion->id} (venta {$refVenta})";
    }

    /**
     * Elimina la transacción de ajuste y devuelve al cliente los puntos previamente descontados.
     */
    private function eliminarAjusteYReembolsar(?TransaccionPuntos $existing): void
    {
        if (!$existing) {
            return;
        }

        $idCliente = $existing->id_cliente;
        $idEmpresa = $existing->id_empresa;
        $reintegro = abs((int) $existing->puntos);
        $existing->delete();

        if ($reintegro <= 0) {
            return;
        }

        $pc = null;
        if ($idCliente && $idEmpresa) {
            $pc = PuntosCliente::where('id_cliente', $idCliente)
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->first();
        }

        if (!$pc) {
            PuntosCliente::create([
                'id_cliente' => $idCliente,
                'id_empresa' => $idEmpresa,
                'puntos_disponibles' => $reintegro,
                'puntos_totales_ganados' => $reintegro,
                'puntos_totales_canjeados' => 0,
                'fecha_ultima_actividad' => now(),
            ]);
            return;
        }

        $pc->increment('puntos_disponibles', $reintegro);
        $pc->increment('puntos_totales_ganados', $reintegro);
        $pc->update(['fecha_ultima_actividad' => now()]);
    }
}
