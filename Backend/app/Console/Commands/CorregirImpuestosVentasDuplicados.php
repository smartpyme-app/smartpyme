<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorregirImpuestosVentasDuplicados extends Command
{
    protected $signature = 'ventas:corregir-impuestos-duplicados
                            {--dry-run : Solo mostrar qué se corregiría, sin ejecutar}
                            {--id_venta= : Corregir solo una venta (opcional)}';

    protected $description = 'Elimina impuestos duplicados en venta_impuestos (mismo id_venta + id_impuesto), dejando uno con la suma de montos';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $idVentaFiltro = $this->option('id_venta');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se aplicarán cambios.');
        }

        $this->info('Buscando impuestos duplicados (mismo id_venta + id_impuesto)...');

        $duplicados = DB::table('venta_impuestos')
            ->select('id_venta', 'id_impuesto', DB::raw('COUNT(*) as total'), DB::raw('SUM(monto) as monto_total'))
            ->when($idVentaFiltro, fn ($q) => $q->where('id_venta', $idVentaFiltro))
            ->groupBy('id_venta', 'id_impuesto')
            ->having('total', '>', 1)
            ->get();

        if ($duplicados->isEmpty()) {
            $this->info('No se encontraron impuestos duplicados.');
            return 0;
        }

        $totalRegistrosDuplicados = $duplicados->sum('total');
        $registrosAEliminar = $totalRegistrosDuplicados - $duplicados->count();

        $this->info("Ventas con duplicados: " . $duplicados->count());
        $this->info("Registros duplicados a eliminar: {$registrosAEliminar} (se conservará 1 por cada par venta+impuesto con la suma de montos).");

        if (!$dryRun && !$this->confirm('¿Ejecutar la corrección?', true)) {
            $this->info('Operación cancelada.');
            return 0;
        }

        try {
            $eliminados = 0;
            $actualizados = 0;

            DB::transaction(function () use ($duplicados, $dryRun, &$eliminados, &$actualizados) {
                foreach ($duplicados as $grupo) {
                    $filas = DB::table('venta_impuestos')
                        ->where('id_venta', $grupo->id_venta)
                        ->where('id_impuesto', $grupo->id_impuesto)
                        ->orderBy('id')
                        ->get();

                    $idConservar = $filas->first()->id;

                    if (!$dryRun) {
                        DB::table('venta_impuestos')
                            ->where('id', $idConservar)
                            ->update(['monto' => (float) $grupo->monto_total]);
                        $actualizados++;

                        $idsEliminar = $filas->where('id', '!=', $idConservar)->pluck('id')->toArray();
                        if (!empty($idsEliminar)) {
                            DB::table('venta_impuestos')->whereIn('id', $idsEliminar)->delete();
                            $eliminados += count($idsEliminar);
                        }
                    }
                }
            });

            if ($dryRun) {
                $this->info('Dry-run: se habrían actualizado ' . $duplicados->count() . ' grupos y eliminado ' . $registrosAEliminar . ' registros.');
            } else {
                $this->info("Listo. Actualizados: {$actualizados} grupos. Registros eliminados: {$eliminados}.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
