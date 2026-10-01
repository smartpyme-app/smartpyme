<?php

namespace App\Console\Commands;

use App\Models\Inventario\AnalisisVentasMensualQueue;
use App\Services\Inventario\AnalisisVentasMensualExportRunner;
use Illuminate\Console\Command;

class ProcesarColaAnalisisVentasMensual extends Command
{
    protected $signature = 'inventario:procesar-cola-analisis-ventas {--limit=0 : Máximo de solicitudes (0 = todas)}';

    protected $description = 'Procesa la cola de reportes de análisis de ventas mensual (inventario)';

    public function handle(AnalisisVentasMensualExportRunner $runner): int
    {
        $limit = (int) $this->option('limit');
        $query = AnalisisVentasMensualQueue::pending()->orderBy('created_at');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            $this->info('No hay solicitudes pendientes.');

            return self::SUCCESS;
        }

        foreach ($items as $item) {
            $this->info("Procesando solicitud #{$item->id} (empresa {$item->id_empresa})...");
            try {
                $runner->processQueueItem($item);
                $this->info("Completado #{$item->id}");
            } catch (\Throwable $e) {
                $this->error("Falló #{$item->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
