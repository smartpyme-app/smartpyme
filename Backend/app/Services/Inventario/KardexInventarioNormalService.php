<?php

namespace App\Services\Inventario;

use App\Constants\KardexDetalleConstants;
use App\Constants\OrigenStockVentaConstants;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Kardex;
use App\Models\Ventas\Detalle as DetalleVenta;
use Illuminate\Support\Collection;

/**
 * ponytail: kardex físico = stock propio (+ remisión consigna propio); pool consigna compra en consigna_movimientos.
 */
class KardexInventarioNormalService
{
    /**
     * @param  Collection<int, Kardex>  $movimientos
     * @return Collection<int, Kardex>
     */
    /**
     * @param  float|null  $stockFisicoActual  Saldo en bodega/lote (post movimientos); ancla corridas sin historial completo en pantalla.
     */
    public function paraKardexFisico(
        Collection $movimientos,
        int $idProducto,
        ?Empresa $empresa,
        ?float $stockFisicoActual = null
    ): Collection {
        if (! $empresa?->isModuloConsignacionesLedgerActivo()) {
            return $movimientos;
        }

        $excluirVentas = $this->idsVentasConConsignaCompra($movimientos, $idProducto);
        $filtrados = $movimientos->filter(function (Kardex $mov) use ($excluirVentas) {
            if ($mov->detalle === 'Compra a consigna') {
                return false;
            }
            if (str_starts_with((string) $mov->detalle, 'Venta')) {
                $idVenta = (int) $mov->referencia;
                if ($idVenta > 0 && in_array($idVenta, $excluirVentas, true)) {
                    return false;
                }
            }

            return true;
        })->values();

        return $this->recalcularExistenciasPropias($filtrados, $stockFisicoActual);
    }

    /**
     * @return list<int>
     */
    private function idsVentasConConsignaCompra(Collection $movimientos, int $idProducto): array
    {
        $ids = $movimientos
            ->filter(fn (Kardex $m) => str_starts_with((string) $m->detalle, 'Venta') && $m->referencia)
            ->pluck('referencia')
            ->map(fn ($r) => (int) $r)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DetalleVenta::query()
            ->whereIn('id_venta', $ids)
            ->where('id_producto', $idProducto)
            ->where('origen_stock', OrigenStockVentaConstants::CONSIGNA_COMPRA)
            ->pluck('id_venta')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Existencias corridas solo con movimientos propios (no stock total del lote).
     *
     * @param  Collection<int, Kardex>  $movimientos
     * @return Collection<int, Kardex>
     */
    private function recalcularExistenciasPropias(Collection $movimientos, ?float $stockFisicoActual = null): Collection
    {
        $ordenados = $movimientos->sortBy([
            ['fecha', 'asc'],
            ['id', 'asc'],
        ])->values();

        $sumDelta = $ordenados
            ->reject(fn (Kardex $mov) => $this->esRegistroContableLiquidacionCliente($mov))
            ->sum(
                fn (Kardex $mov) => (float) ($mov->entrada_cantidad ?? 0) - (float) ($mov->salida_cantidad ?? 0)
            );
        $existencia = $stockFisicoActual !== null
            ? $stockFisicoActual - $sumDelta
            : 0.0;

        foreach ($ordenados as $mov) {
            $entrada = (float) ($mov->entrada_cantidad ?? 0);
            $salida = (float) ($mov->salida_cantidad ?? 0);
            if (! $this->esRegistroContableLiquidacionCliente($mov)) {
                $existencia += $entrada - $salida;
            }
            $mov->total_cantidad = $existencia;
            $costo = (float) ($mov->costo_unitario ?? 0);
            $mov->total_valor = round($existencia * $costo, 2);
        }

        return $movimientos;
    }

    private function esRegistroContableLiquidacionCliente(Kardex $mov): bool
    {
        return $mov->detalle === KardexDetalleConstants::LIQUIDACION_CONSIGNA_CLIENTE;
    }
}
