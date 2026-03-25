<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EliminarVentasRango extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ventas:eliminar-rango {desde=1085876 : ID inicial} {hasta=1086730 : ID final}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina ventas y sus registros relacionados en el rango de IDs especificado';

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

        $this->info("Eliminando ventas del ID {$desde} al {$hasta} y sus registros relacionados...");

        try {
            DB::transaction(function () use ($desde, $hasta) {
                $idsVenta = range($desde, $hasta);

                // 3. Detalles de venta
                $deleted = DB::table('detalles_venta')
                    ->whereBetween('id_venta', [$desde, $hasta])
                    ->delete();
                $this->info("  - Detalles venta: {$deleted} registros");

                // 10. Ventas
                $deleted = DB::table('ventas')
                    ->whereBetween('id', [$desde, $hasta])
                    ->delete();
                $this->info("  - Ventas: {$deleted} registros");
            });

            $this->info('Operación completada correctamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
