<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Ventas\Venta;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;

class ImportarVentasDatos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ventas:importar-datos 
                            {--ruta-ventas=datos/ventas.php : Ruta al archivo ventas.php}
                            {--ruta-detalles=datos/detalles_venta.php : Ruta al archivo detalles_venta.php}
                            {--solo-insertar : Solo insertar datos, no actualizar inventario/kardex}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa ventas y detalles desde archivos PHP en la carpeta datos, actualizando inventario y kardex';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $basePath = base_path();
        $rutaVentas = $basePath . '/' . ltrim($this->option('ruta-ventas'), '/');
        $rutaDetalles = $basePath . '/' . ltrim($this->option('ruta-detalles'), '/');
        $soloInsertar = $this->option('solo-insertar');

        if (!file_exists($rutaVentas) || !file_exists($rutaDetalles)) {
            $this->error("Archivos no encontrados. Ventas: {$rutaVentas}, Detalles: {$rutaDetalles}");
            return 1;
        }

        $ventas = [];
        $detalles_venta = [];
        require $rutaVentas;
        require $rutaDetalles;

        if (empty($ventas) || empty($detalles_venta)) {
            $this->error('Los archivos están vacíos o no definen $ventas y $detalles_venta.');
            return 1;
        }

        $ventasColumns = array_flip(Schema::getColumnListing('ventas'));
        $detallesColumns = array_flip(Schema::getColumnListing('detalles_venta'));

        $this->info('Insertando ' . count($ventas) . ' ventas...');

        try {
            DB::transaction(function () use ($ventas, $detalles_venta, $ventasColumns, $detallesColumns, $soloInsertar) {
                $ventasFiltered = [];
                foreach ($ventas as $venta) {
                    $filtered = array_intersect_key($venta, $ventasColumns);
                    if (!empty($filtered)) {
                        $ventasFiltered[] = $filtered;
                    }
                }
                foreach (array_chunk($ventasFiltered, 200) as $chunk) {
                    DB::table('ventas')->insert($chunk);
                }

                $this->info('Insertando ' . count($detalles_venta) . ' detalles de venta...');

                $detallesFiltered = [];
                foreach ($detalles_venta as $detalle) {
                    if (isset($detalle['subtotal']) && !isset($detalle['sub_total'])) {
                        $detalle['sub_total'] = $detalle['subtotal'];
                    }
                    unset($detalle['subtotal'], $detalle['id']);
                    $filtered = array_intersect_key($detalle, $detallesColumns);
                    if (!empty($filtered)) {
                        $detallesFiltered[] = $filtered;
                    }
                }
                foreach (array_chunk($detallesFiltered, 200) as $chunk) {
                    DB::table('detalles_venta')->insert($chunk);
                }

                $this->info('Detalles insertados: ' . count($detallesFiltered));

                if (!$soloInsertar) {
                    $this->info('Actualizando inventario y kardex...');
                    $this->actualizarInventarioYKardex($detalles_venta, $ventas);
                }
            });

            $this->info('Importación completada correctamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Actualiza inventario y kardex para cada detalle, igual que al generar una venta nueva.
     */
    protected function actualizarInventarioYKardex(array $detalles_venta, array $ventas): void
    {
        $ventasById = collect($ventas)->keyBy('id');
        $total = count($detalles_venta);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($detalles_venta as $det) {
            $ventaData = $ventasById->get($det['id_venta'] ?? null);
            if (!$ventaData) {
                $bar->advance();
                continue;
            }

            if (($ventaData['cotizacion'] ?? 0) == 1) {
                $bar->advance();
                continue;
            }

            $producto = Producto::withoutGlobalScopes()->find($det['id_producto'] ?? null);
            if (!$producto || $producto->tipo === 'Servicio') {
                $bar->advance();
                continue;
            }

            $cantidad = (float) ($det['cantidad'] ?? 0);
            $precio = (float) ($det['precio'] ?? 0);
            $idBodega = $ventaData['id_bodega'] ?? null;

            if (!$idBodega || $cantidad <= 0) {
                $bar->advance();
                continue;
            }

            $inventario = Inventario::withoutGlobalScopes()
                ->where('id_producto', $det['id_producto'])
                ->where('id_bodega', $idBodega)
                ->first();

            if ($inventario) {
                if (!empty($det['lote_id'])) {
                    $lote = \App\Models\Inventario\Lote::withoutGlobalScopes()->find($det['lote_id']);
                    if ($lote && $lote->stock >= $cantidad) {
                        $lote->decrement('stock', $cantidad);
                    }
                }

                $inventario->decrement('stock', $cantidad);

                $venta = Venta::withoutGlobalScopes()->find($det['id_venta']);
                if ($venta) {
                    $inventario->kardex($venta, $cantidad, $precio);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
