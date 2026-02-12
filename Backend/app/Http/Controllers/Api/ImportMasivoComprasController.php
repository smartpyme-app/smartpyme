<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Importación masiva de compras por lotes: proveedores → compras → detalles_compra.
 * Llamar repetidamente hasta que done=true. Cambios: id_proveedor e id_compra (viejo→nuevo).
 *
 * Uso: GET /api/import-masivo-compras?step=proveedores&offset=0
 */
class ImportMasivoComprasController extends Controller
{
    protected string $datosPath = 'datos';
    protected string $progressFile = 'import_masivo_compras_progress.json';
    protected int $batchProveedores = 100;
    protected int $batchCompras = 50;
    protected int $batchDetalles = 150;

    public function __invoke(Request $request): JsonResponse
    {
        set_time_limit(120);
        ini_set('memory_limit', '2048M');

        $step = $request->query('step', 'proveedores');
        $offset = (int) $request->query('offset', 0);

        $proveedoresPath = base_path($this->datosPath . DIRECTORY_SEPARATOR . 'proveedores.php');
        $comprasPath = base_path($this->datosPath . DIRECTORY_SEPARATOR . 'compras.php');
        $detallesPath = base_path($this->datosPath . DIRECTORY_SEPARATOR . 'detalles_compra.php');

        if (!is_readable($proveedoresPath) || !is_readable($comprasPath) || !is_readable($detallesPath)) {
            return response()->json([
                'error' => 'No se encontraron los archivos de datos.',
                'rutas' => [$proveedoresPath, $comprasPath, $detallesPath],
            ], 404);
        }

        try {
            if ($step === 'proveedores') {
                return $this->procesarProveedores($proveedoresPath, $offset);
            }
            if ($step === 'compras') {
                return $this->procesarCompras($comprasPath, $offset);
            }
            if ($step === 'detalles') {
                return $this->procesarDetalles($detallesPath, $offset);
            }
            return response()->json(['error' => 'step inválido. Use: proveedores, compras o detalles'], 400);
        } catch (\Throwable $e) {
            Log::error('ImportMasivoCompras', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function getProgress(): array
    {
        if (!Storage::exists($this->progressFile)) {
            return ['mapProveedor' => [], 'mapCompra' => []];
        }
        $data = json_decode(Storage::get($this->progressFile), true);
        return [
            'mapProveedor' => $data['mapProveedor'] ?? [],
            'mapCompra' => $data['mapCompra'] ?? [],
        ];
    }

    protected function saveProgress(array $mapProveedor, array $mapCompra): void
    {
        Storage::put($this->progressFile, json_encode(compact('mapProveedor', 'mapCompra')));
    }

    protected function procesarProveedores(string $path, int $offset): JsonResponse
    {
        $proveedores = $this->loadPhpArray($path, 'proveedores');
        $total = count($proveedores);
        $batch = array_slice($proveedores, $offset, $this->batchProveedores);
        $progress = $this->getProgress();
        $mapProveedor = $progress['mapProveedor'];
        $fillable = (new Proveedor())->getFillable();

        DB::beginTransaction();
        try {
            foreach ($batch as $row) {
                $idViejo = $row['id'] ?? null;
                $idEmpresa = (string) ($row['id_empresa'] ?? '');
                if ($idViejo === null || $idViejo === '' || $idEmpresa === '') continue;

                $key = $idEmpresa . '|' . $idViejo;
                $existente = $this->buscarProveedorExistente($row, $idEmpresa);
                if ($existente) {
                    $mapProveedor[$key] = $existente->id;
                    continue;
                }
                $data = $this->soloFillable($row, $fillable);
                unset($data['id']);
                $proveedor = Proveedor::withoutGlobalScopes()->create($data);
                $mapProveedor[$key] = $proveedor->id;
            }
            $this->saveProgress($mapProveedor, $progress['mapCompra']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOffset = $offset + count($batch);
        $done = $newOffset >= $total;
        $nextStep = $done ? 'compras' : 'proveedores';
        $nextOffset = $done ? 0 : $newOffset;

        return response()->json([
            'step' => 'proveedores',
            'offset' => $newOffset,
            'total' => $total,
            'processed' => count($batch),
            'done' => $done,
            'next_url' => url('/api/import-masivo-compras?step=' . $nextStep . '&offset=' . $nextOffset),
            'message' => $done ? 'Proveedores completados. Siguiente: compras.' : "Proveedores {$newOffset}/{$total}",
        ]);
    }

    protected function buscarProveedorExistente(array $row, string $idEmpresa): ?Proveedor
    {
        $v = fn($x) => ($x === null || $x === '' || $x === 'null') ? '' : trim((string) $x);
        $nit = $v($row['nit'] ?? null);
        $dui = $v($row['dui'] ?? null);
        $correo = $v($row['correo'] ?? null);
        $nombre = $v($row['nombre'] ?? null);
        $apellido = $v($row['apellido'] ?? null);
        $nombreEmpresa = $v($row['nombre_empresa'] ?? null);
        $q = fn() => Proveedor::withoutGlobalScopes()->where('id_empresa', $idEmpresa);

        if ($nit !== '') { $f = $q()->where('nit', $nit)->first(); if ($f) return $f; }
        if ($dui !== '') { $f = $q()->where('dui', $dui)->first(); if ($f) return $f; }
        if ($correo !== '') { $f = $q()->where('correo', $correo)->first(); if ($f) return $f; }
        if (($row['tipo'] ?? '') === 'Empresa' && $nombreEmpresa !== '') { $f = $q()->where('nombre_empresa', $nombreEmpresa)->first(); if ($f) return $f; }
        if ($nombre !== '' || $apellido !== '') {
            $qb = $q(); if ($nombre !== '') $qb->where('nombre', $nombre); if ($apellido !== '') $qb->where('apellido', $apellido);
            $f = $qb->first(); if ($f) return $f;
        }
        return null;
    }

    protected function procesarCompras(string $path, int $offset): JsonResponse
    {
        $progress = $this->getProgress();
        $mapProveedor = $progress['mapProveedor'];
        if (empty($mapProveedor) && $offset === 0) {
            return response()->json(['error' => 'Primero complete proveedores (step=proveedores&offset=0)'], 400);
        }

        $compras = $this->loadPhpArray($path, 'compras');
        $total = count($compras);
        $batch = array_slice($compras, $offset, $this->batchCompras);
        $mapCompra = $progress['mapCompra'];
        $fillable = (new Compra())->getFillable();

        DB::beginTransaction();
        try {
            foreach ($batch as $row) {
                $idViejo = $row['id'] ?? null;
                if ($idViejo === null || $idViejo === '') continue;

                $data = $this->soloFillable($row, $fillable);
                unset($data['id']);
                $idProveedorViejo = $row['id_proveedor'] ?? null;
                $idEmpresa = (string) ($row['id_empresa'] ?? '');
                $data['id_proveedor'] = null;
                if ($idProveedorViejo !== null && $idProveedorViejo !== '' && $idEmpresa !== '') {
                    $clave = $idEmpresa . '|' . $idProveedorViejo;
                    $data['id_proveedor'] = $mapProveedor[$clave] ?? $mapProveedor[$idEmpresa . '|' . (int) $idProveedorViejo] ?? null;
                    if ($data['id_proveedor'] === null) {
                        $existente = Proveedor::withoutGlobalScopes()
                            ->where('id_empresa', $idEmpresa)
                            ->where('id', (int) $idProveedorViejo)
                            ->first();
                        if ($existente) {
                            $mapProveedor[$clave] = $existente->id;
                            $data['id_proveedor'] = $existente->id;
                        } else {
                            $proveedorPlaceholder = Proveedor::withoutGlobalScopes()->create([
                                'nombre_empresa' => 'Proveedor importación #' . $idProveedorViejo,
                                'tipo' => 'Empresa',
                                'enable' => 1,
                                'id_empresa' => $idEmpresa,
                                'id_usuario' => $row['id_usuario'] ?? null,
                            ]);
                            $mapProveedor[$clave] = $proveedorPlaceholder->id;
                            $data['id_proveedor'] = $proveedorPlaceholder->id;
                        }
                    }
                }
                $compra = Compra::withoutGlobalScopes()->create($data);
                $mapCompra[$idViejo] = $compra->id;
            }
            $this->saveProgress($mapProveedor, $mapCompra);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOffset = $offset + count($batch);
        $done = $newOffset >= $total;
        $nextStep = $done ? 'detalles' : 'compras';
        $nextOffset = $done ? 0 : $newOffset;

        return response()->json([
            'step' => 'compras',
            'offset' => $newOffset,
            'total' => $total,
            'processed' => count($batch),
            'done' => $done,
            'next_url' => url('/api/import-masivo-compras?step=' . $nextStep . '&offset=' . $nextOffset),
            'message' => $done ? 'Compras completadas. Siguiente: detalles.' : "Compras {$newOffset}/{$total}",
        ]);
    }

    protected function procesarDetalles(string $path, int $offset): JsonResponse
    {
        $progress = $this->getProgress();
        $mapCompra = $progress['mapCompra'];
        if (empty($mapCompra) && $offset === 0) {
            return response()->json(['error' => 'Primero complete compras (step=compras&offset=0)'], 400);
        }

        $detalles = $this->loadPhpArray($path, 'detalles_compra');
        $total = count($detalles);
        $batch = array_slice($detalles, $offset, $this->batchDetalles);
        $fillable = (new Detalle())->getFillable();
        $created = 0;

        $idComprasUnicas = array_unique(array_filter(array_map(function ($r) use ($mapCompra) {
            $idc = $r['id_compra'] ?? null;
            if ($idc === null || $idc === '') return null;
            return $mapCompra[$idc] ?? $mapCompra[(int) $idc] ?? null;
        }, $batch)));
        $compras = Compra::withoutGlobalScopes()->whereIn('id', $idComprasUnicas)->get()->keyBy('id');

        DB::beginTransaction();
        try {
            foreach ($batch as $row) {
                $idCompraVieja = $row['id_compra'] ?? null;
                if ($idCompraVieja === null || $idCompraVieja === '') continue;

                $idCompraNueva = $mapCompra[$idCompraVieja] ?? $mapCompra[(int) $idCompraVieja] ?? null;
                if ($idCompraNueva === null) continue;

                $compra = $compras->get($idCompraNueva);
                if (!$compra) continue;

                $data = $this->soloFillable($row, $fillable);
                unset($data['id']);
                $data['id_compra'] = $idCompraNueva;
                Detalle::create($data);
                $created++;

                if ($compra->cotizacion == 0) {
                    $this->actualizarInventarioCompra($compra, $data, $row);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOffset = $offset + count($batch);
        $done = $newOffset >= $total;

        $response = [
            'step' => 'detalles',
            'offset' => $newOffset,
            'total' => $total,
            'processed' => count($batch),
            'created' => $created,
            'done' => $done,
            'message' => $done ? 'Importación de compras completada.' : "Detalles {$newOffset}/{$total}",
        ];
        if (!$done) {
            $response['next_url'] = url('/api/import-masivo-compras?step=detalles&offset=' . $newOffset);
        } else {
            Storage::delete($this->progressFile);
        }
        return response()->json($response);
    }

    protected function loadPhpArray(string $path, string $variableName): array
    {
        $result = [];
        if ($variableName === 'proveedores') {
            (function () use ($path, &$result) { $proveedores = []; include $path; $result = is_array($proveedores) ? $proveedores : []; })();
        } elseif ($variableName === 'compras') {
            (function () use ($path, &$result) { $compras = []; include $path; $result = is_array($compras) ? $compras : []; })();
        } elseif ($variableName === 'detalles_compra') {
            (function () use ($path, &$result) { $detalles_compra = []; include $path; $result = is_array($detalles_compra) ? $detalles_compra : []; })();
        } else {
            throw new \InvalidArgumentException("Variable no soportada: {$variableName}");
        }
        return $result;
    }

    protected function actualizarInventarioCompra(\App\Models\Compras\Compra $compra, array $det, array $row): void
    {
        $idProducto = $det['id_producto'] ?? $row['id_producto'] ?? null;
        $cantidad = (float) ($det['cantidad'] ?? $row['cantidad'] ?? 0);
        $costo = (float) ($det['costo'] ?? $row['costo'] ?? 0);

        if (!$idProducto || $cantidad <= 0) return;

        $producto = Producto::withoutGlobalScopes()->with('inventarios')->find($idProducto);
        if ($producto) {
            $stockAnterior = $producto->inventarios->sum('stock') ?? 0;
            $stockTotal = $stockAnterior + $cantidad;
            $costoPromedio = $stockTotal > 0
                ? (($stockAnterior * $producto->costo) + ($cantidad * $costo)) / $stockTotal
                : $costo;
            $producto->costo_anterior = $producto->costo;
            $producto->costo = $costo;
            $producto->costo_promedio = $costoPromedio;
            $producto->save();
        }

        $inventario = Inventario::withoutGlobalScopes()
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $compra->id_bodega)
            ->lockForUpdate()
            ->first();

        if ($inventario) {
            $inventario->stock += $cantidad;
            $inventario->save();
            $inventario->kardex($compra, $cantidad);
        }
    }

    protected function soloFillable(array $row, array $fillable): array
    {
        $out = [];
        foreach ($fillable as $key) {
            if (array_key_exists($key, $row)) {
                $v = $row[$key];
                $out[$key] = ($v === 'null' || $v === 'NULL') ? null : $v;
            }
        }
        return $out;
    }
}
