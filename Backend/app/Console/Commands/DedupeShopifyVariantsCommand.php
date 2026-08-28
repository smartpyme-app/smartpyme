<?php

namespace App\Console\Commands;

use App\Models\Inventario\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DedupeShopifyVariantsCommand extends Command
{
    protected $signature = 'shopify:dedupe-variantes {--empresa=} {--dry-run}';

    protected $description = 'Consolida filas duplicadas de productos con el mismo shopify_variant_id (1 sola fila por variante)';

    /**
     * Tablas con FK id_producto a reasignar hacia la fila canónica.
     * (inventario y producto_impuestos se manejan aparte por sus restricciones únicas.)
     */
    private const TABLAS_ID_PRODUCTO = [
        'kardexs',
        'ajustes',
        'lotes',
        'productos_imagenes',
        'traslados',
        'producto_precios',
        'detalles_venta',
        'detalles_compra',
        'detalles_promocion',
        'producto_composiciones',
        'inventario_entrada_detalles',
        'inventario_salida_detalles',
        'producto_traslado_detalles',
        'detalles_compuesto_venta',
        'detalles_devolucion_venta',
        'detalles_devolucion_compra',
        'transformacion_detalles',
        'promociones',
        'producto_presentaciones',
    ];

    public function handle(): int
    {
        $empresaId = $this->option('empresa');
        $dryRun = (bool) $this->option('dry-run');

        $query = Producto::withoutGlobalScopes()
            ->whereNotNull('shopify_variant_id');

        if ($empresaId) {
            $query->where('id_empresa', $empresaId);
        }

        $grupos = $query->get()
            ->groupBy(function (Producto $p) {
                return $p->id_empresa . ':' . $p->shopify_variant_id;
            })
            ->filter(function ($g) {
                return $g->count() > 1;
            });

        if ($grupos->isEmpty()) {
            $this->info('No se encontraron filas duplicadas por shopify_variant_id.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Se encontraron %d grupos duplicados%s.',
            $grupos->count(),
            $dryRun ? ' (dry-run, sin cambios)' : ''
        ));

        $consolidados = 0;

        foreach ($grupos as $grupo) {
            $canonica = $this->elegirCanonica($grupo);
            $duplicadas = $grupo->filter(function ($p) use ($canonica) {
                return $p->id !== $canonica->id;
            });

            $this->line(sprintf(
                '  variant_id=%s canonical=%d duplicadas=%s',
                $canonica->shopify_variant_id,
                $canonica->id,
                $duplicadas->pluck('id')->toJson()
            ));

            if ($dryRun) {
                $consolidados++;
                continue;
            }

            try {
                DB::transaction(function () use ($canonica, $duplicadas) {
                    // 1) Fusionar valores no vacíos de las duplicadas en la canónica
                    $this->fusionar($canonica, $duplicadas);

                    foreach ($duplicadas as $dup) {
                        // 2) Consolidar inventario (SUM por bodega)
                        $this->consolidarInventario($canonica, $dup);

                        // 3) Reasignar dependencias de tablas con id_producto
                        $this->reasignarDependencias($canonica, $dup);

                        // 4) Consolidar impuestos (pivot) si aplica
                        $this->consolidarImpuestos($canonica, $dup);

                        // 5) Soft-delete de la fila duplicada
                        $dup->delete();
                    }
                });

                $consolidados++;
                $this->info(sprintf(
                    '  Consolidado variant_id=%s -> fila %d',
                    $canonica->shopify_variant_id,
                    $canonica->id
                ));
            } catch (\Exception $e) {
                Log::error('DedupeShopifyVariants: error consolidando', [
                    'variant_id' => $canonica->shopify_variant_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error('  Error consolidando variant_id=' . $canonica->shopify_variant_id . ': ' . $e->getMessage());
            }
        }

        $this->info("Consolidados: {$consolidados} grupos.");
        return self::SUCCESS;
    }

    /**
     * Elige la fila canónica: la que tenga codigo (SKU) no vacío; si empatan, la más reciente.
     */
    private function elegirCanonica($grupo): Producto
    {
        return $grupo->sortByDesc(function (Producto $p) {
            $conCodigo = !empty($p->codigo) ? 1 : 0;
            $reciente = $p->updated_at ? $p->updated_at->getTimestamp() : 0;
            return $conCodigo . sprintf('%012d', $reciente) . sprintf('%012d', $p->id);
        })->first();
    }

    /**
     * Copia valores no vacíos de las duplicadas hacia la canónica.
     */
    private function fusionar(Producto $canonica, $duplicadas): void
    {
        $camposTexto = [
            'nombre',
            'nombre_variante',
            'codigo',
            'barcode',
            'descripcion',
            'descripcion_completa',
            'option1_name',
            'option1_value',
            'option2_name',
            'option2_value',
            'option3_name',
            'option3_value',
            'shopify_sku',
        ];

        foreach ($duplicadas as $dup) {
            foreach ($camposTexto as $campo) {
                if (empty($canonica->{$campo}) && !empty($dup->{$campo})) {
                    $canonica->{$campo} = $dup->{$campo};
                }
            }
        }

        $canonica->save();
    }

    /**
     * Consolida el inventario de la duplicada hacia la canónica (SUM por bodega),
     * respetando la restricción única id_producto + id_bodega.
     */
    private function consolidarInventario(Producto $canonica, Producto $dup): void
    {
        $rows = DB::table('inventario')
            ->where('id_producto', $dup->id)
            ->get();

        foreach ($rows as $row) {
            $existente = DB::table('inventario')
                ->where('id_producto', $canonica->id)
                ->where('id_bodega', $row->id_bodega)
                ->whereNull('deleted_at')
                ->first();

            if ($existente) {
                DB::table('inventario')
                    ->where('id', $existente->id)
                    ->update([
                        'stock' => (float) $existente->stock + (float) $row->stock,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('inventario')
                    ->where('id', $row->id)
                    ->update([
                        'id_producto' => $canonica->id,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Reasigna id_producto en las tablas dependientes hacia la canónica.
     */
    private function reasignarDependencias(Producto $canonica, Producto $dup): void
    {
        foreach (self::TABLAS_ID_PRODUCTO as $tabla) {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable($tabla)) {
                    continue;
                }
                DB::table($tabla)
                    ->where('id_producto', $dup->id)
                    ->update(['id_producto' => $canonica->id]);
            } catch (\Exception $e) {
                Log::warning('DedupeShopifyVariants: no se pudo reasignar en ' . $tabla, [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Consolida la tabla pivote producto_impuestos (id_producto + id_impuesto).
     */
    private function consolidarImpuestos(Producto $canonica, Producto $dup): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('producto_impuestos')) {
            return;
        }

        $rows = DB::table('producto_impuestos')
            ->where('id_producto', $dup->id)
            ->get();

        foreach ($rows as $row) {
            $existe = DB::table('producto_impuestos')
                ->where('id_producto', $canonica->id)
                ->where('id_impuesto', $row->id_impuesto)
                ->exists();

            if ($existe) {
                DB::table('producto_impuestos')->where('id', $row->id)->delete();
            } else {
                DB::table('producto_impuestos')
                    ->where('id', $row->id)
                    ->update(['id_producto' => $canonica->id]);
            }
        }
    }
}
