<?php

namespace App\Console\Commands;

use App\Services\Compras\CompraInventarioAplicacionService;
use Illuminate\Console\Command;

class AplicarInventarioCompra extends Command
{
    protected $signature = 'compras:aplicar-inventario
                            {id : ID de la compra}
                            {--dry-run : Muestra acciones sin escribir en base de datos}
                            {--force : Aplica aunque ya exista kardex de entrada (duplica stock)}';

    protected $description = 'Aplica stock y kardex de una compra guardada sin inventario (p. ej. DTE con actualizar_inventario desactivado)';

    public function handle(CompraInventarioAplicacionService $service): int
    {
        $id = (int) $this->argument('id');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($force && !$dryRun) {
            if (!$this->confirm("¿Forzar aplicación en compra #{$id}? Puede duplicar stock si ya hubo movimientos.")) {
                $this->warn('Cancelado.');

                return self::SUCCESS;
            }
        }

        $result = $service->aplicar($id, $dryRun, $force);

        if (!empty($result['message'])) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        foreach ($result['lineas'] as $linea) {
            $this->line($linea);
        }

        foreach ($result['errores'] as $detalleId => $msg) {
            $this->error("Detalle {$detalleId}: {$msg}");
        }

        $this->info(sprintf(
            'Resumen: %d aplicadas, %d omitidas%s.',
            $result['aplicados'],
            $result['omitidos'],
            $dryRun ? ' (simulación)' : ''
        ));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
