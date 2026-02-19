<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Ventas\Venta;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;

class ImportarVentasRecuperar extends Command
{
    protected $signature = 'ventas:importar-recuperar
                            {--archivo= : Nombre del archivo (ej: ventas_perdidas_2026-02-11_2026-02-12.php)}
                            {--ruta=datos/recuperar : Carpeta donde está el archivo}
                            {--chunk=100 : Registros por lote para no saturar el servidor}
                            {--delay=100 : Milisegundos de pausa entre lotes (reduce carga en prod)}
                            {--dry-run : Solo validar, no insertar}
                            {--solo-insertar : No actualizar inventario ni kardex}';

    protected $description = 'Importa ventas perdidas desde archivo PHP exportado (ejecutar en producción)';

    public function handle()
    {
        $archivo = $this->option('archivo');
        $rutaBase = base_path(ltrim($this->option('ruta'), '/'));
        $chunk = (int) $this->option('chunk');
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');
        $soloInsertar = $this->option('solo-insertar');

        if (empty($archivo)) {
            $this->error('Debes especificar --archivo=nombre_archivo.php');
            return 1;
        }

        $rutaArchivo = $rutaBase . '/' . $archivo;
        if (!file_exists($rutaArchivo)) {
            $this->error("Archivo no encontrado: {$rutaArchivo}");
            return 1;
        }

        $datos = require $rutaArchivo;
        if (!is_array($datos) || !isset($datos['clientes'], $datos['ventas'], $datos['detalles_venta'])) {
            $this->error('Archivo inválido: debe retornar array con clientes, ventas y detalles_venta.');
            return 1;
        }

        $metadata = $datos['metadata'] ?? [];
        $this->info("Importando datos del " . ($metadata['fecha_inicio'] ?? '') . ' al ' . ($metadata['fecha_fin'] ?? ''));

        if ($dryRun) {
            $this->warn('Modo dry-run: no se insertarán datos.');
        }

        $clienteNovaToVps = [];
        $ventaNovaToVps = [];

        $connection = config('database.default');
        $columnsClientes = array_flip(Schema::connection($connection)->getColumnListing('clientes'));
        $columnsVentas = array_flip(Schema::connection($connection)->getColumnListing('ventas'));
        $columnsDetalles = array_flip(Schema::connection($connection)->getColumnListing('detalles_venta'));

        try {
            if (!$dryRun) {
                DB::beginTransaction();
            }

            // 1. Insertar clientes
            $this->info('Insertando ' . count($datos['clientes']) . ' clientes...');
            $chunksClientes = array_chunk($datos['clientes'], $chunk);
            foreach ($chunksClientes as $i => $chunkItems) {
                if (!$dryRun) {
                    foreach ($chunkItems as $item) {
                        $data = $this->filtrarColumnas($item['data'], $columnsClientes);
                        unset($data['id']);
                        if (!empty($data)) {
                            $nuevoId = DB::table('clientes')->insertGetId($data);
                            $clienteNovaToVps[$item['id_nova']] = (int) $nuevoId;
                        }
                    }
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                }
                $this->output->write('.');
            }
            $this->newLine();
            $this->info('Clientes insertados: ' . count($clienteNovaToVps));

            // 2. Insertar ventas (con id_cliente resuelto)
            $this->info('Insertando ' . count($datos['ventas']) . ' ventas...');
            $chunksVentas = array_chunk($datos['ventas'], $chunk);
            foreach ($chunksVentas as $chunkItems) {
                if (!$dryRun) {
                    foreach ($chunkItems as $item) {
                        $data = $item['data'];
                        $idClienteRef = $data['id_cliente_ref'] ?? null;
                        unset($data['id_cliente_ref']);

                        if ($idClienteRef !== null) {
                            if (is_string($idClienteRef) && substr($idClienteRef, 0, 5) === 'nova_') {
                                $idNova = (int) substr($idClienteRef, 5);
                                $data['id_cliente'] = $clienteNovaToVps[$idNova] ?? null;
                            } else {
                                $data['id_cliente'] = (int) $idClienteRef;
                            }
                        }

                        $data = $this->filtrarColumnas($data, $columnsVentas);
                        unset($data['id']);
                        if (!empty($data)) {
                            $nuevoId = DB::table('ventas')->insertGetId($data);
                            $ventaNovaToVps[$item['id_nova']] = (int) $nuevoId;
                        }
                    }
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                }
                $this->output->write('.');
            }
            $this->newLine();
            $this->info('Ventas insertadas: ' . count($ventaNovaToVps));

            // 3. Insertar detalles_venta (con id_venta resuelto)
            $this->info('Insertando ' . count($datos['detalles_venta']) . ' detalles...');
            $detallesParaInsert = [];
            foreach ($datos['detalles_venta'] as $item) {
                $idVentaRef = $item['id_venta_ref'] ?? null;
                if (!is_string($idVentaRef) || substr($idVentaRef, 0, 5) !== 'nova_') {
                    continue;
                }
                $idNova = (int) substr($idVentaRef, 5);
                $idVentaVps = $ventaNovaToVps[$idNova] ?? null;
                if (!$idVentaVps) {
                    continue;
                }

                $data = $item['data'];
                $data['id_venta'] = $idVentaVps;
                if (isset($data['subtotal']) && !isset($data['sub_total'])) {
                    $data['sub_total'] = $data['subtotal'];
                }
                unset($data['subtotal'], $data['id']);

                $data = $this->filtrarColumnas($data, $columnsDetalles);
                if (!empty($data)) {
                    $detallesParaInsert[] = $data;
                }
            }

            if (!$dryRun && !empty($detallesParaInsert)) {
                foreach (array_chunk($detallesParaInsert, $chunk) as $chunkDetalles) {
                    DB::table('detalles_venta')->insert($chunkDetalles);
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                    $this->output->write('.');
                }
                $this->newLine();
            }

            $this->info('Detalles insertados: ' . count($detallesParaInsert));

            if (!$soloInsertar && !$dryRun && !empty($detallesParaInsert)) {
                $this->info('Actualizando inventario y kardex...');
                $this->actualizarInventarioYKardex($datos, $ventaNovaToVps);
            }

            if (!$dryRun) {
                DB::commit();
                $this->info('Importación completada correctamente.');
            }

            return 0;
        } catch (\Throwable $e) {
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

    /**
     * Actualiza inventario y kardex para cada detalle (misma lógica que ImportarVentasDatos).
     */
    protected function actualizarInventarioYKardex(array $datos, array $ventaNovaToVps): void
    {
        $ventasById = collect($datos['ventas'])->keyBy('id_nova');
        $total = count($datos['detalles_venta']);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($datos['detalles_venta'] as $item) {
            $idVentaRef = $item['id_venta_ref'] ?? null;
            if (!is_string($idVentaRef) || substr($idVentaRef, 0, 5) !== 'nova_') {
                $bar->advance();
                continue;
            }
            $idVentaNova = (int) substr($idVentaRef, 5);
            $ventaItem = $ventasById->get($idVentaNova);
            if (!$ventaItem) {
                $bar->advance();
                continue;
            }

            $ventaData = $ventaItem['data'];
            $det = $item['data'];

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

                $idVentaKardex = $ventaNovaToVps[$idVentaNova] ?? null;
                if ($idVentaKardex) {
                    $venta = Venta::withoutGlobalScopes()->find($idVentaKardex);
                    if ($venta) {
                        $fechaVenta = $ventaData['fecha'] ?? $venta->fecha ?? null;
                        $inventario->kardex($venta, $cantidad, $precio, null, $fechaVenta);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
