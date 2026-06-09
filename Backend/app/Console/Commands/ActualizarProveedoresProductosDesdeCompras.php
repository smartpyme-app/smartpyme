<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Inventario\Proveedor as ProductoProveedor;

class ActualizarProveedoresProductosDesdeCompras extends Command
{
    protected $signature = 'productos:actualizar-proveedores-compras
                            {id_empresa : ID de la empresa}
                            {--dry-run : Mostrar cambios sin aplicarlos}';

    protected $description = 'Asigna proveedor a productos según las compras de la empresa (prevalece la compra más reciente)';

    public function handle(): int
    {
        $idEmpresa = (int) $this->argument('id_empresa');
        $dryRun = (bool) $this->option('dry-run');

        $empresa = Empresa::find($idEmpresa);
        if (!$empresa) {
            $this->error("Empresa con id {$idEmpresa} no encontrada.");
            return 1;
        }

        $this->info("Procesando compras de: {$empresa->nombre} (id: {$idEmpresa})");
        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirán cambios.');
        }

        $compras = Compra::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('cotizacion', 0)
            ->where('estado', '!=', 'Anulada')
            ->whereNotNull('id_proveedor')
            ->where('id_proveedor', '>', 1)
            ->orderBy('fecha')
            ->orderBy('id')
            ->with(['detalles' => function ($query) {
                $query->whereNotNull('id_producto')
                    ->select('id', 'id_compra', 'id_producto');
            }])
            ->get(['id', 'id_proveedor', 'fecha']);

        if ($compras->isEmpty()) {
            $this->warn('No se encontraron compras válidas para procesar.');
            return 0;
        }

        $this->info("Compras a analizar: {$compras->count()}");

        $proveedorPorProducto = [];
        foreach ($compras as $compra) {
            foreach ($compra->detalles as $detalle) {
                $proveedorPorProducto[(int) $detalle->id_producto] = (int) $compra->id_proveedor;
            }
        }

        $idsProducto = array_keys($proveedorPorProducto);
        $this->info('Productos únicos en compras: ' . count($idsProducto));

        $productosEmpresa = DB::table('productos')
            ->where('id_empresa', $idEmpresa)
            ->whereNull('deleted_at')
            ->whereIn('id', $idsProducto)
            ->pluck('id')
            ->flip();

        $proveedorActual = DB::table('producto_proveedores')
            ->whereIn('id_producto', $idsProducto)
            ->orderBy('id')
            ->get(['id_producto', 'id_proveedor'])
            ->groupBy('id_producto')
            ->map(fn ($rows) => (int) $rows->last()->id_proveedor);

        $creados = 0;
        $sinCambios = 0;
        $omitidos = 0;

        $bar = $this->output->createProgressBar(count($proveedorPorProducto));
        $bar->start();

        foreach ($proveedorPorProducto as $idProducto => $idProveedor) {
            $bar->advance();

            if (!$productosEmpresa->has($idProducto)) {
                $omitidos++;
                continue;
            }

            if ($proveedorActual->get($idProducto) === $idProveedor) {
                $sinCambios++;
                continue;
            }

            if (!$dryRun) {
                ProductoProveedor::create([
                    'id_producto' => $idProducto,
                    'id_proveedor' => $idProveedor,
                ]);
            }

            $creados++;
        }

        $bar->finish();
        $this->newLine();

        $prefijo = $dryRun ? '[Dry-run] Se asignarían' : 'Se asignaron';
        $this->info("{$prefijo} proveedor a {$creados} producto(s).");
        $this->info("Sin cambios (proveedor ya correcto): {$sinCambios}.");

        if ($omitidos > 0) {
            $this->warn("Omitidos (producto no pertenece a la empresa): {$omitidos}.");
        }

        return 0;
    }
}
