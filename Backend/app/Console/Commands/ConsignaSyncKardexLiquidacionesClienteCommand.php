<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Console\Command;

class ConsignaSyncKardexLiquidacionesClienteCommand extends Command
{
    protected $signature = 'consigna:sync-kardex-liquidaciones-cliente {--empresa= : ID empresa}';

    protected $description = 'Crea filas informativas en kardex físico por liquidaciones consigna cliente ya registradas';

    public function handle(ConsignaMovimientoService $service): int
    {
        $empresaId = $this->option('empresa');
        if (! $empresaId) {
            $this->error('Indique --empresa=ID');

            return self::FAILURE;
        }

        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa?->isModuloConsignacionesLedgerActivo()) {
            $this->error('Ledger de consigna no activo para esta empresa.');

            return self::FAILURE;
        }

        $creados = $service->sincronizarKardexLiquidacionesCliente((int) $empresaId);
        $this->info("Kardex físico: {$creados} fila(s) nuevas de liquidación consigna cliente.");

        return self::SUCCESS;
    }
}
