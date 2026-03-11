<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Compras\Compra;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;

/**
 * Exporta productos desde sp_nova para compras que existen en DB_DATABASE pero
 * cuyos productos solo existen en sp_nova. Genera archivos para ejecución en producción.
 * Incluye inventario y kardex si la compra fue aplicada (cotizacion=0, estado!=Anulada).
 *
 * Uso:
 * 1. Exportar (ejecutar donde tengas acceso a ambas DBs):
 *    php artisan compras:sync-productos --ids=28401,28440,28389 --exportar
 *
 * 2. Prueba en local (sin escribir):
 *    php artisan compras:sync-productos --archivo=compras_productos_28401_28440_28389.php --dry-run
 *
 * 3. Importar en producción (solo DB_DATABASE):
 *    php artisan compras:sync-productos --archivo=compras_productos_28401_28440_28389.php
 *
 * 4. Importar sin inventario/kardex (solo productos + detalles, para probar):
 *    php artisan compras:sync-productos --archivo=... --sin-inventario
 */
class SincronizarProductosCompras extends Command
{
    protected $signature = 'compras:sync-productos
                            {--ids= : IDs de compras separados por coma (ej: 28401,28440,28389)}
                            {--exportar : Exportar productos a archivo PHP para producción}
                            {--archivo= : Archivo PHP a importar (ejecutar en prod)}
                            {--ruta=datos/compras : Carpeta para archivos de export/import}
                            {--generar-sql : Al exportar, también genera scripts SQL (productos + imagenes)}
                            {--sin-inventario : No actualizar inventario ni kardex (útil para pruebas)}
                            {--dry-run : Solo mostrar qué se haría, no escribir}';

    protected $description = 'Sincroniza productos de compras entre bases: exporta desde sp_nova, importa y actualiza detalles en DB_DATABASE';

    public function handle()
    {
        $exportar = $this->option('exportar');
        $archivo = $this->option('archivo');
        $ids = $this->option('ids');
        $rutaBase = base_path(ltrim($this->option('ruta'), '/'));
        $dryRun = $this->option('dry-run');

        if ($exportar) {
            if (empty($ids)) {
                $this->error('Para exportar debes especificar --ids=28401,28440,28389');
                return 1;
            }
            return $this->exportar($ids, $rutaBase, $dryRun, $this->option('generar-sql'));
        }

        if (!empty($archivo)) {
            $rutaArchivo = strpos($archivo, '/') !== false ? $archivo : $rutaBase . '/' . $archivo;
            if (!file_exists($rutaArchivo)) {
                $this->error("Archivo no encontrado: {$rutaArchivo}");
                return 1;
            }
            return $this->importar($rutaArchivo, $dryRun, $this->option('sin-inventario'));
        }

        $this->error('Debes especificar --exportar con --ids= o --archivo= para importar.');
        return 1;
    }

    /**
     * Exporta productos desde sp_nova a un archivo PHP.
     */
    protected function exportar(string $ids, string $rutaBase, bool $dryRun, bool $generarSql = false): int
    {
        $idsCompras = array_map('intval', array_filter(explode(',', $ids)));
        if (empty($idsCompras)) {
            $this->error('IDs inválidos.');
            return 1;
        }

        $this->info('Obteniendo detalles de compra desde DB_DATABASE...');
        $detalles = DB::connection(config('database.default'))
            ->table('detalles_compra')
            ->whereIn('id_compra', $idsCompras)
            ->get();

        $idsProducto = $detalles->pluck('id_producto')->unique()->filter()->values()->all();
        if (empty($idsProducto)) {
            $this->warn('No hay productos en los detalles de esas compras.');
            return 0;
        }

        $this->info('Productos a buscar: ' . implode(', ', $idsProducto));

        $this->info('Obteniendo productos desde sp_nova...');
        $productos = DB::connection('mysql_sp_nova')
            ->table('productos')
            ->whereIn('id', $idsProducto)
            ->get();

        if ($productos->isEmpty()) {
            $this->error('No se encontraron productos en sp_nova para esos IDs.');
            return 1;
        }

        $tablaImagenes = $this->getNombreTablaImagenes('mysql_sp_nova');
        $this->info("Obteniendo imágenes desde {$tablaImagenes}...");
        $imagenes = DB::connection('mysql_sp_nova')
            ->table($tablaImagenes)
            ->whereIn('id_producto', $idsProducto)
            ->get();

        $detallesParaMapeo = $detalles->map(function ($d) {
            return [
                'id_compra' => $d->id_compra,
                'id_producto' => $d->id_producto,
                'id_detalle' => $d->id,
                'cantidad' => (float) ($d->cantidad ?? 0),
                'costo' => (float) ($d->costo ?? 0),
                'lote_id' => $d->lote_id ?? null,
            ];
        })->toArray();

        $datos = [
            'metadata' => [
                'fecha_export' => date('Y-m-d H:i:s'),
                'ids_compras' => $idsCompras,
                'ids_productos' => $idsProducto,
            ],
            'productos' => $productos->map(fn ($p) => ['id_nova' => $p->id, 'data' => (array) $p])->toArray(),
            'imagenes' => $imagenes->map(fn ($i) => ['id_producto_nova' => $i->id_producto, 'data' => (array) $i])->toArray(),
            'detalles_mapeo' => $detallesParaMapeo,
        ];

        $nombreArchivo = 'compras_productos_' . implode('_', $idsCompras) . '.php';
        $rutaArchivo = rtrim($rutaBase, '/') . '/' . $nombreArchivo;

        if (!is_dir($rutaBase)) {
            mkdir($rutaBase, 0755, true);
        }

        if ($dryRun) {
            $this->warn("[Dry-run] Se escribiría en: {$rutaArchivo}");
            $this->info('Productos: ' . count($productos) . ', Imágenes: ' . count($imagenes));
            return 0;
        }

        $contenido = "<?php\n\nreturn " . var_export($datos, true) . ";\n";
        file_put_contents($rutaArchivo, $contenido);

        if ($generarSql) {
            $this->generarScriptsSql($datos, $rutaBase, $idsCompras);
        }

        $this->info("Exportado correctamente a: {$rutaArchivo}");
        $this->info("Para ejecutar en producción: php artisan compras:sync-productos --archivo={$nombreArchivo}");
        return 0;
    }

    /**
     * Importa productos desde archivo y actualiza detalles_compra.
     * Opcionalmente actualiza inventario y kardex si la compra fue aplicada.
     */
    protected function importar(string $rutaArchivo, bool $dryRun, bool $sinInventario = false): int
    {
        $datos = require $rutaArchivo;
        if (!is_array($datos) || !isset($datos['productos'], $datos['detalles_mapeo'])) {
            $this->error('Archivo inválido.');
            return 1;
        }

        $metadata = $datos['metadata'] ?? [];
        $this->info('Importando productos para compras: ' . implode(', ', $metadata['ids_compras'] ?? []));

        $connection = config('database.default');
        $columnsProductos = array_flip(Schema::connection($connection)->getColumnListing('productos'));
        $tablaImagenes = $this->getNombreTablaImagenes($connection);
        $columnsImagenes = array_flip(Schema::connection($connection)->getColumnListing($tablaImagenes));

            $productoNovaToVps = [];
        $productos = $datos['productos'];
        $imagenes = $datos['imagenes'] ?? [];
        $detallesMapeo = $datos['detalles_mapeo'];

        try {
            if (!$dryRun) {
                DB::beginTransaction();
            }

            $this->info('Insertando ' . count($productos) . ' productos como nuevos...');
            foreach ($productos as $item) {
                $data = $this->filtrarColumnas($item['data'], $columnsProductos);
                unset($data['id']);
                if (!empty($data) && !$dryRun) {
                    $nuevoId = DB::connection($connection)->table('productos')->insertGetId($data);
                    $productoNovaToVps[$item['id_nova']] = (int) $nuevoId;
                } elseif ($dryRun) {
                    $productoNovaToVps[$item['id_nova']] = $item['id_nova'];
                }
            }

            $this->info('Insertando imágenes...');
            foreach ($imagenes as $item) {
                $idProductoNova = $item['id_producto_nova'] ?? null;
                $nuevoIdProducto = $productoNovaToVps[$idProductoNova] ?? null;
                if (!$nuevoIdProducto) {
                    continue;
                }
                $data = $this->filtrarColumnas($item['data'], $columnsImagenes);
                unset($data['id']);
                $data['id_producto'] = $nuevoIdProducto;
                if (!empty($data) && !$dryRun) {
                    DB::connection($connection)->table($tablaImagenes)->insert($data);
                }
            }

            $this->info('Actualizando detalles_compra...');
            $actualizados = 0;
            foreach ($detallesMapeo as $det) {
                $idProductoNova = $det['id_producto'] ?? null;
                $nuevoIdProducto = $productoNovaToVps[$idProductoNova] ?? null;
                if (!$nuevoIdProducto || $nuevoIdProducto == $idProductoNova) {
                    continue;
                }
                if (!$dryRun) {
                    DB::connection($connection)->table('detalles_compra')
                        ->where('id', $det['id_detalle'])
                        ->update(['id_producto' => $nuevoIdProducto]);
                }
                $actualizados++;
            }

            if (!$sinInventario && !$dryRun) {
                $this->actualizarInventarioYKardex($detallesMapeo, $productoNovaToVps);
            } elseif ($sinInventario) {
                $this->warn('Inventario y kardex omitidos (--sin-inventario).');
            } elseif ($dryRun) {
                $this->warn('[Dry-run] Se omitiría inventario/kardex.');
            }

            if (!$dryRun) {
                DB::commit();
            }

            $this->info("Importación completada. Detalles actualizados: {$actualizados}");
            return 0;
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    protected function filtrarColumnas(array $data, array $columns): array
    {
        return array_intersect_key($data, $columns);
    }

    protected function getNombreTablaImagenes(string $connection): string
    {
        return Schema::connection($connection)->hasTable('productos_imagenes')
            ? 'productos_imagenes'
            : 'producto_imagenes';
    }

    /**
     * Genera scripts SQL para inserciones manuales (referencia).
     * Nota: La actualización de detalles_compra requiere el mapeo de IDs nuevos,
     * por eso debe ejecutarse con el comando --archivo= en producción.
     */
    protected function generarScriptsSql(array $datos, string $rutaBase, array $idsCompras): void
    {
        $idsStr = implode('_', $idsCompras);

        // Script INSERT productos
        $lines = ["-- Productos para compras " . implode(', ', $idsCompras), "USE " . env('DB_DATABASE', 'vps') . ";", ""];
        foreach ($datos['productos'] as $item) {
            $d = $item['data'];
            unset($d['id']);
            $cols = array_keys($d);
            $vals = array_map(function ($v) {
                if ($v === null) return 'NULL';
                if (is_numeric($v) && !is_string($v)) return (string) $v;
                if (is_bool($v)) return $v ? '1' : '0';
                return "'" . addslashes((string) $v) . "'";
            }, array_values($d));
            $lines[] = "INSERT INTO productos (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");";
        }
        file_put_contents($rutaBase . "/productos_insert_{$idsStr}.sql", implode("\n", $lines));
        $this->info("SQL productos: {$rutaBase}/productos_insert_{$idsStr}.sql");

        // Script UPDATE detalles (con placeholders - los IDs nuevos se obtienen tras INSERT)
        $lines = ["-- ACTUALIZAR detalles_compra después de insertar productos", "-- Ejecutar via: php artisan compras:sync-productos --archivo=compras_productos_{$idsStr}.php", ""];
        foreach ($datos['detalles_mapeo'] as $d) {
            $lines[] = "-- Detalle id={$d['id_detalle']}: id_producto {$d['id_producto']} -> [NUEVO_ID]";
        }
        file_put_contents($rutaBase . "/detalles_update_{$idsStr}.sql", implode("\n", $lines));
        $this->info("SQL referencias: {$rutaBase}/detalles_update_{$idsStr}.sql");
    }

    /**
     * Actualiza inventario y kardex para cada detalle.
     * Solo aplica si la compra: cotizacion=0 (no es orden) y estado!=Anulada.
     * Crea inventario si no existe.
     */
    protected function actualizarInventarioYKardex(array $detallesMapeo, array $productoNovaToVps): void
    {
        $idsCompras = array_unique(array_column($detallesMapeo, 'id_compra'));
        $compras = Compra::withoutGlobalScopes()->whereIn('id', $idsCompras)->get()->keyBy('id');

        $bar = $this->output->createProgressBar(count($detallesMapeo));
        $bar->start();

        $inventarioOk = 0;
        $inventarioOmitido = 0;

        foreach ($detallesMapeo as $det) {
            $compra = $compras->get($det['id_compra'] ?? null);
            if (!$compra) {
                $bar->advance();
                continue;
            }

            // Validar: solo si la compra fue aplicada (no cotización, no anulada)
            if (($compra->cotizacion ?? 0) == 1) {
                $inventarioOmitido++;
                $bar->advance();
                continue;
            }
            if (($compra->estado ?? '') === 'Anulada') {
                $inventarioOmitido++;
                $bar->advance();
                continue;
            }

            $idProductoNova = $det['id_producto'] ?? null;
            $nuevoIdProducto = $productoNovaToVps[$idProductoNova] ?? null;
            if (!$nuevoIdProducto) {
                $bar->advance();
                continue;
            }

            $producto = Producto::withoutGlobalScopes()->find($nuevoIdProducto);
            if (!$producto) {
                $bar->advance();
                continue;
            }
            if (($producto->tipo ?? '') === 'Servicio') {
                $bar->advance();
                continue;
            }

            $cantidad = (float) ($det['cantidad'] ?? 0);
            $costo = (float) ($det['costo'] ?? 0);
            $idBodega = $compra->id_bodega ?? null;

            if (!$idBodega || $cantidad <= 0) {
                $bar->advance();
                continue;
            }

            $inventario = Inventario::withoutGlobalScopes()
                ->where('id_producto', $nuevoIdProducto)
                ->where('id_bodega', $idBodega)
                ->first();

            if (!$inventario) {
                $inventario = Inventario::firstOrCreate(
                    [
                        'id_producto' => $nuevoIdProducto,
                        'id_bodega' => $idBodega,
                    ],
                    ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                );
            }

            $stockAnterior = $producto->inventarios()->withoutGlobalScopes()->sum('stock') ?? 0;
            $stockTotal = $stockAnterior + $cantidad;
            $costoPromedio = $stockTotal > 0
                ? (($stockAnterior * ($producto->costo ?? 0)) + ($cantidad * $costo)) / $stockTotal
                : $costo;

            $producto->costo_anterior = $producto->costo;
            $producto->costo = $costo;
            $producto->costo_promedio = $costoPromedio;
            $producto->save();

            $loteId = $det['lote_id'] ?? null;
            if ($loteId) {
                $lote = Lote::withoutGlobalScopes()->find($loteId);
                if ($lote && $lote->id_producto == $nuevoIdProducto && $lote->id_bodega == $idBodega) {
                    $lote->increment('stock', $cantidad);
                }
            }

            $inventario->increment('stock', $cantidad);
            $inventario->kardex($compra, $cantidad);

            $inventarioOk++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Inventario/kardex: {$inventarioOk} aplicados." . ($inventarioOmitido > 0 ? " {$inventarioOmitido} omitidos (cotización/anulada/servicio)." : ''));
    }
}
