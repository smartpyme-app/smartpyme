<?php

namespace App\Console\Commands;

use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;
use App\Services\Inventario\ConsignaCustodiaService;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Console\Command;

class ConsignaLedgerBackfillCommand extends Command
{
    protected $signature = 'consigna:ledger-backfill
                            {--empresa= : ID empresa}
                            {--dry-run : Solo mostrar conteos, sin insertar}';

    protected $description = 'Genera movimientos de consigna desde compras/ventas históricas (no modifica kardex clásico)';

    public function handle(ConsignaMovimientoService $service): int
    {
        $empresaId = $this->option('empresa');
        if (! $empresaId) {
            $this->error('Indique --empresa=ID');

            return self::FAILURE;
        }

        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            $this->error("Empresa {$empresaId} no encontrada");

            return self::FAILURE;
        }

        if (! $empresa->isModuloConsignacionesLedgerActivo()) {
            $this->error('Ledger de consigna no activo para esta empresa (funcionalidad + Mi cuenta).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $entradas = 0;
        $ventas = 0;

        Compra::query()
            ->where('id_empresa', $empresaId)
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->with('detalles')
            ->orderBy('id')
            ->chunk(50, function ($compras) use ($service, $dryRun, &$entradas) {
                /** @var Compra $compra */
                foreach ($compras as $compra) {
                    foreach ($compra->detalles as $detalle) {
                        $key = 'compra-entrada-' . $compra->id . '-det-' . $detalle->id;
                        if (ConsignaMovimiento::query()->where('id_empresa', $compra->id_empresa)->where('idempotency_key', $key)->exists()) {
                            continue;
                        }
                        $entradas++;
                        if (! $dryRun) {
                            $service->entradaProveedorDesdeCompra($compra, $detalle);
                        }
                    }
                }
            });

        DetalleVenta::query()
            ->whereHas('venta', function ($q) use ($empresaId) {
                $q->where('id_empresa', $empresaId)->where('cotizacion', 0);
            })
            ->with('venta')
            ->orderBy('id')
            ->chunk(100, function ($detalles) use ($service, $dryRun, &$ventas) {
                /** @var DetalleVenta $detalle */
                foreach ($detalles as $detalle) {
                    $venta = $detalle->venta;
                    if (! $venta) {
                        continue;
                    }
                    $key = 'venta-' . $venta->id . '-det-' . $detalle->id;
                    if (ConsignaMovimiento::query()->where('id_empresa', $venta->id_empresa)->where('idempotency_key', $key)->exists()) {
                        continue;
                    }
                    $ventas++;
                    if (! $dryRun) {
                        $service->desdeVentaDetalle($venta, $detalle);
                    }
                }
            });

        $liquidaciones = 0;
        Venta::query()
            ->where('id_empresa', $empresaId)
            ->where('estado', 'Pagada')
            ->where('cotizacion', 0)
            ->with('detalles')
            ->orderBy('id')
            ->chunk(50, function ($ventas) use ($service, $dryRun, &$liquidaciones) {
                /** @var Venta $venta */
                foreach ($ventas as $venta) {
                    foreach ($venta->detalles as $detalle) {
                        $entregaKey = 'venta-' . $venta->id . '-det-' . $detalle->id;
                        $liqKey = 'venta-liq-' . $venta->id . '-det-' . $detalle->id;
                        $tieneEntrega = ConsignaMovimiento::query()
                            ->where('id_empresa', $venta->id_empresa)
                            ->where('idempotency_key', $entregaKey)
                            ->exists();
                        if (! $tieneEntrega) {
                            continue;
                        }
                        if (ConsignaMovimiento::query()
                            ->where('id_empresa', $venta->id_empresa)
                            ->where('idempotency_key', $liqKey)
                            ->exists()) {
                            continue;
                        }
                        $liquidaciones++;
                        if (! $dryRun) {
                            $service->liquidacionClienteDesdeVenta($venta, $detalle, (float) $detalle->cantidad);
                        }
                    }
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '')
            . "Entradas consigna: {$entradas}, líneas venta: {$ventas}, liquidaciones cliente: {$liquidaciones}");

        if (! $dryRun) {
            $filasCustodia = app(ConsignaCustodiaService::class)->reconstruirDesdeLedger((int) $empresaId);
            $this->info("Custodias cliente reconstruidas: {$filasCustodia} filas.");
            $kardexLiq = $service->sincronizarKardexLiquidacionesCliente((int) $empresaId);
            $this->info("Kardex físico (liquidación consigna cliente): {$kardexLiq} fila(s) nuevas.");
        }

        return self::SUCCESS;
    }
}
