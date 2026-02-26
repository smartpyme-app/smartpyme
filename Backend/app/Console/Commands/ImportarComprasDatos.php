<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Compras\Compra;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;

class ImportarComprasDatos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compras:importar-datos 
                            {--ruta-compras=datos/compras.php : Ruta al archivo compras.php}
                            {--ruta-detalles=datos/detalles_compra.php : Ruta al archivo detalles_compra.php}
                            {--solo-insertar : Solo insertar datos, no actualizar inventario/kardex}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa compras y detalles desde archivos PHP en la carpeta datos, actualizando inventario y kardex';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $basePath = base_path();
        $rutaCompras = $basePath . '/' . ltrim($this->option('ruta-compras'), '/');
        $rutaDetalles = $basePath . '/' . ltrim($this->option('ruta-detalles'), '/');
        $soloInsertar = $this->option('solo-insertar');

        if (!file_exists($rutaCompras) || !file_exists($rutaDetalles)) {
            $this->error("Archivos no encontrados. Compras: {$rutaCompras}, Detalles: {$rutaDetalles}");
            return 1;
        }

        $compras = [];
        $detalles_compra = [];
        require $rutaCompras;
        require $rutaDetalles;

        if (empty($compras) || empty($detalles_compra)) {
            $this->error('Los archivos están vacíos o no definen $compras y $detalles_compra.');
            return 1;
        }

        $comprasColumns = array_flip(Schema::getColumnListing('compras'));
        $detallesColumns = array_flip(Schema::getColumnListing('detalles_compra'));

        $this->info('Insertando ' . count($compras) . ' compras...');

        try {
            DB::transaction(function () use ($compras, $detalles_compra, $comprasColumns, $detallesColumns, $soloInsertar) {
                $mapeoIdCompra = [];
                $ultimoId = (int) DB::table('compras')->max('id');

                foreach ($compras as $compra) {
                    $idOriginal = $compra['id'] ?? null;
                    $filtered = array_intersect_key($compra, $comprasColumns);
                    unset($filtered['id']);
                    if (!empty($filtered)) {
                        DB::table('compras')->insert($filtered);
                        $ultimoId++;
                        if ($idOriginal !== null) {
                            $mapeoIdCompra[$idOriginal] = $ultimoId;
                        }
                    }
                }

                $this->info('Insertando ' . count($detalles_compra) . ' detalles de compra...');

                $detallesFiltered = [];
                foreach ($detalles_compra as $detalle) {
                    unset($detalle['id']);
                    $idCompraOriginal = $detalle['id_compra'] ?? null;
                    if ($idCompraOriginal === null || !isset($mapeoIdCompra[$idCompraOriginal])) {
                        continue;
                    }
                    $detalle['id_compra'] = $mapeoIdCompra[$idCompraOriginal];
                    $filtered = array_intersect_key($detalle, $detallesColumns);
                    if (!empty($filtered)) {
                        $detallesFiltered[] = $filtered;
                    }
                }
                foreach (array_chunk($detallesFiltered, 200) as $chunk) {
                    DB::table('detalles_compra')->insert($chunk);
                }

                $this->info('Detalles insertados: ' . count($detallesFiltered));

                if (!$soloInsertar) {
                    $this->info('Actualizando inventario y kardex...');
                    $this->actualizarInventarioYKardex($detalles_compra, $compras, $mapeoIdCompra);
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
     * Actualiza inventario y kardex para cada detalle, igual que al generar una compra nueva.
     */
    protected function actualizarInventarioYKardex(array $detalles_compra, array $compras, array $mapeoIdCompra = []): void
    {
        $comprasById = collect($compras)->keyBy('id');
        $total = count($detalles_compra);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($detalles_compra as $det) {
            $compraData = $comprasById->get($det['id_compra'] ?? null);
            if (!$compraData) {
                $bar->advance();
                continue;
            }

            if (($compraData['cotizacion'] ?? 0) == 1) {
                $bar->advance();
                continue;
            }

            $producto = Producto::withoutGlobalScopes()->find($det['id_producto'] ?? null);
            if (!$producto) {
                $bar->advance();
                continue;
            }

            if ($producto->tipo === 'Servicio') {
                $bar->advance();
                continue;
            }

            $cantidad = (float) ($det['cantidad'] ?? 0);
            $costo = (float) ($det['costo'] ?? 0);
            $idBodega = $compraData['id_bodega'] ?? null;

            if (!$idBodega || $cantidad <= 0) {
                $bar->advance();
                continue;
            }

            $inventario = Inventario::withoutGlobalScopes()
                ->where('id_producto', $det['id_producto'])
                ->where('id_bodega', $idBodega)
                ->first();

            if ($inventario) {
                $stockAnterior = $producto->inventarios()->withoutGlobalScopes()->sum('stock') ?? 0;
                $stockTotal = $stockAnterior + $cantidad;
                if ($stockTotal > 0) {
                    $costoPromedio = (($stockAnterior * $producto->costo) + ($cantidad * $costo)) / $stockTotal;
                } else {
                    $costoPromedio = $costo;
                }
                $producto->costo_anterior = $producto->costo;
                $producto->costo = $costo;
                $producto->costo_promedio = $costoPromedio;
                $producto->save();

                if (!empty($det['lote_id'])) {
                    $lote = \App\Models\Inventario\Lote::withoutGlobalScopes()->find($det['lote_id']);
                    if ($lote) {
                        $lote->increment('stock', $cantidad);
                    }
                }

                $inventario->increment('stock', $cantidad);

                $idCompraKardex = $mapeoIdCompra[$det['id_compra']] ?? $det['id_compra'];
                $compra = Compra::withoutGlobalScopes()->find($idCompraKardex);
                if ($compra) {
                    $inventario->kardex($compra, $cantidad);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
