<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaDocumentosService;
use Illuminate\Console\Command;

class ConsignaAsegurarDocumentosDevolucionCommand extends Command
{
    protected $signature = 'consigna:asegurar-documentos-devolucion {--empresa=}';

    protected $description = 'Crea documento Devolución de consigna por sucursal (empresas con ledger activo)';

    public function handle(ConsignaDocumentosService $documentosService): int
    {
        $idEmpresa = $this->option('empresa');
        $query = Empresa::query();
        if ($idEmpresa !== null && $idEmpresa !== '') {
            $query->where('id', (int) $idEmpresa);
        }

        $total = 0;
        foreach ($query->cursor() as $empresa) {
            if (! $empresa->isModuloConsignacionesLedgerActivo()) {
                $this->line("Empresa {$empresa->id}: ledger off, omitida.");

                continue;
            }
            $creados = $documentosService->asegurarDocumentosLedger($empresa);
            $total += $creados;
            $this->info("Empresa {$empresa->id}: {$creados} documento(s) creado(s).");
        }

        $this->info("Total documentos nuevos: {$total}");

        return self::SUCCESS;
    }
}
