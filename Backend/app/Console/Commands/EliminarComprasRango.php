<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EliminarComprasRango extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compras:eliminar-rango {desde=28316 : ID inicial} {hasta=28347 : ID final}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina compras y sus registros relacionados en el rango de IDs especificado';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $desde = (int) $this->argument('desde');
        $hasta = (int) $this->argument('hasta');

        if ($desde > $hasta) {
            $this->error('El ID inicial no puede ser mayor al ID final.');
            return 1;
        }

        $this->info("Eliminando compras del ID {$desde} al {$hasta} y sus registros relacionados...");

        try {
            DB::transaction(function () use ($desde, $hasta) {
                // 1. Detalles de devoluciones (de devoluciones de estas compras)
                $idsDevolucion = DB::table('devoluciones_compra')
                    ->whereBetween('id_compra', [$desde, $hasta])
                    ->pluck('id');

                if ($idsDevolucion->isNotEmpty()) {
                    $deleted = DB::table('detalles_devolucion_compra')
                        ->whereIn('id_devolucion_compra', $idsDevolucion)
                        ->delete();
                    $this->info("  - Detalles devolución compra: {$deleted} registros");
                }

                // 2. Devoluciones de compra
                $deleted = DB::table('devoluciones_compra')
                    ->whereBetween('id_compra', [$desde, $hasta])
                    ->delete();
                $this->info("  - Devoluciones compra: {$deleted} registros");

                // 3. Abonos de compra
                $deleted = DB::table('abonos_compras')
                    ->whereBetween('id_compra', [$desde, $hasta])
                    ->delete();
                $this->info("  - Abonos compra: {$deleted} registros");

                // 4. Detalles de compra
                $deleted = DB::table('detalles_compra')
                    ->whereBetween('id_compra', [$desde, $hasta])
                    ->delete();
                $this->info("  - Detalles compra: {$deleted} registros");

                // 5. Compras
                $deleted = DB::table('compras')
                    ->whereBetween('id', [$desde, $hasta])
                    ->delete();
                $this->info("  - Compras: {$deleted} registros");
            });

            $this->info('Operación completada correctamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
